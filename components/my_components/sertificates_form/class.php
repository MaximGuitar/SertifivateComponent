<?
use Bitrix\Main\Loader;
use CIBlockElement;
use Bitrix\Main\IO\File;
use Bitrix\Main\Mail\Event;
use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Engine\ActionFilter;
use \Bitrix\Main\HttpResponse;

require_once ('tcpdf/tcpdf.php');//Подключаем библиотеку для формирования PDF
class CertificateActivationComponent extends \CBitrixComponent implements Controllerable
{

    /**
     * Конфигурирование доступных действий для контроллера.
     * @return array Массив доступных действий с предфильтрами и постфильтрами.
     */
    public function configureActions()
    {
        return [
            'ActivateSertificate' => [
                'prefilters' => [
                ],
                'postfilters' => []
            ]
        ];
    }

    /**
     * Метод активации сертификата. Выполняет последовательность действий:
     * 1. Поиск сертификата по коду.
     * 2. Обновление статуса сертификата.
     * 3. Генерация PDF-файла с информацией о сертификате.
     * 4. Отправка сертификата по почте.
     * @param string $sertCode Код сертификата.
     * @return HttpResponse Возвращает HTML-шаблон компонента.
     */
    public function ActivateSertificateAction($sertCode)
    {
        $Date = date("d.m.Y H:i:s");
        // Вызов первой функции и получение ее результата
        $IDSertificate = $this->FindCertificate($sertCode);
        if (!$IDSertificate)
            $this->arResult['status'] = "Сертификат не найден";
        $UpdateStatus = $this->UpdateCertificate($IDSertificate);
        if (!$UpdateStatus)
            $this->arResult['status'] = "Ошибка активации сертификата";
        $PDFPath = $this->generatePDF($sertCode, $Date);
        if (!$PDFPath)
            $this->arResult['status'] = "Ошибка генерации PDF";
        $SendStatus = $this->sendSertificate($PDFPath, $Date, $sertCode);
        if (!$SendStatus)
            $this->arResult['status'] = "Ошибка отправки письма";

        if ($SendStatus && $PDFPath && $UpdateStatus && $IDSertificate) {
            $this->arResult['status'] = "Сертификат успешно активирован";
            $this->arResult['SertPath'] = $PDFPath;
        }
        $this->getData();
        $response = $this->getComponentTemplate();
        return $response;
    }

    /**
     * Получение HTML-шаблона компонента.
     * @return HttpResponse Возвращает HTTP-ответ с HTML-шаблоном компонента.
     */
    protected function getComponentTemplate()
    {
        // Возвращаем HTML шаблон компонента
        ob_start();
        $this->includeComponentTemplate();
        $templateHTML = ob_get_clean();

        $response = new HttpResponse();
        $response->addHeader("Content-Type", "text/html; charset=UTF-8");
        $response->setContent($templateHTML);
        return $response;
    }

    /**
     * Получение данных для компонента.
     * @return void 
     */
    protected function getData()
    {
        // Получаем параметры компонента
        $iblockId = $this->arParams["IBLOCK_ID"];

        $curUserID = $this->getCurUserID();  // Получаем ID текущего пользователя

        // Проверяем, загружен ли модуль "iblock"
        if (!Loader::includeModule('iblock')) {
            throw new \Exception('Модуль "iblock" не загружен');
        }

        // Получаем элементы инфоблока
        $arFilter = [
            "IBLOCK_ID" => $iblockId,
            "ACTIVE" => "Y",
            "PROPERTY_ACTIVITY_VALUE" => "Y",
            "PROPERTY_USER_ACTIVITY_ID" => $curUserID,
        ];
        $arSelect = [
            "ID",
            "NAME",
            "CODE",
            "PROPERTY_ACTIVITY_DATE",
            "PROPERTY_ACTIVITY",
        ];

        $res = \CIBlockElement::GetList([], $arFilter, false, false, $arSelect);
        while ($ob = $res->Fetch()) {
            $this->arResult["ELEMENTS"][] = $ob;
        }

    }

    /**
     * Поиск сертификата по коду.
     * @param string $sertCode Код сертификата.
     * @return mixed ID сертификата или false, если сертификат не найден.
     */
    public function FindCertificate($sertCode)
    {
        if (!Loader::includeModule('iblock')) {
            return [
                'status' => 'error',
                'errors' => [
                    [
                        'message' => 'Модуль "iblock" не загружен'
                    ]
                ]
            ];
        }

        $iblockId = $this->arParams["IBLOCK_ID"];

        $arFilter = [
            "IBLOCK_ID" => $iblockId,
            "CODE" => $sertCode,
            "ACTIVE" => "Y",
            "PROPERTY_ACTIVITY" => false,
        ];

        $findedSert = \CIBlockElement::GetList(
            array(),
            $arFilter,
            false,
            false,
            ["ID"]
        );

        $sertElemID = $findedSert->Fetch()['ID'];
        if ($sertElemID && $sertCode !== '') {
            return $sertElemID;
        } else {
            return false;
        }
    }

    /**
     * Обновление статуса сертификата.
     * @param int $sertElemID ID элемента сертификата в инфоблоке.
     * @return boolean Результат обновления статуса сертификата (true или false).
     */
    protected function UpdateCertificate($sertElemID)
    {

        $curUserID = $this->getCurUserID();  // Получаем ID текущего пользователя
        $curDate = date("d.m.Y H:i:s");

        // Массив свойств для обновления элемента
        $arProps = [
            "PROPERTY_VALUES" => [
                "ACTIVITY" => true,
                "USER_ACTIVITY_ID" => $curUserID,
                "ACTIVITY_DATE" => $curDate
            ]
        ];
        // Обновление элемента инфоблока
        $el = new CIBlockElement;
        $res = $el->Update($sertElemID, $arProps);

        // Проверяем успешность обновления элемента
        if ($res) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Генерация PDF-файла с информацией о сертификате.
     * @param string $code Код сертификата.
     * @param string $date Дата активации сертификата.
     * @return string|false Путь к сгенерированному PDF-файлу или false в случае ошибки.
     */
    protected function generatePDF($code, $date)
    {
        Loader::includeModule('fileman');
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('Your Name');
        $pdf->SetTitle('Certificate Activation');
        $pdf->SetSubject('Certificate');
        $pdf->SetKeywords('Certificate, Activation');

        $pdf->AddPage();
        $pdf->SetFont('times', 'B', 12);
        $pdf->Cell(0, 10, 'Activation Code: ' . $code, 0, 1);
        $pdf->Cell(0, 10, 'Activation Date: ' . $date, 0, 1);

        $fileContent = $pdf->Output('certificate.pdf', 'S');
        $fileArray = [
            "name" => "certificate.pdf",
            "type" => "application/pdf",
            "content" => $fileContent,
        ];
        $fileId = \CFile::SaveFile($fileArray, "certificate");
        if ($fileId) {
            $filePath = \CFile::GetPath($fileId);
            return $filePath;
        } else {
            return false;
        }
    }

    /**
     * Отправка сертификата по почте.
     * @param string $filePath Путь к файлу сертификата.
     * @param string $Date Дата активации сертификата.
     * @param string $SertficateCode Код сертификата.
     * @return boolean Результат отправки сертификата (true или false).
     */
    protected function sendSertificate($filePath, $Date, $SertficateCode)
    {
        global $USER;
        $emailTo = $USER->GetEmail();
        $message = "Вы активировали сертификат $SertficateCode в $Date по московскому времени.";

        $attachments = [
            [
                'FILE_ID' => \CFile::MakeFileArray($_SERVER["DOCUMENT_ROOT"] . $filePath),
                'NAME' => 'certificate.pdf',
            ],
        ];

        $result = Event::send([
            'EVENT_NAME' => 'SEND_CERTIFICATE',
            'LID' => SITE_ID,
            'C_FIELDS' => [
                'EMAIL_TO' => $emailTo,
                "EMAIL" => $emailTo,
                'MESSAGE' => $message . " " . $_SERVER['HTTP_HOST'] . $filePath,
                "USER_ID" => $this->getCurUserID(),
                "USER_NAME" => $USER->GetFullName(),
            ],
            'MESSAGE_ID' => null,
            'FILE' => $attachments,
        ]);

        if ($result->isSuccess()) {
            return true;
        } else {
            return false;
        }
    }


    /**
     * Получение ID текущего пользователя.
     * @return int ID текущего пользователя.
     */
    protected function getCurUserID()
    {
        global $USER;
        return $USER->GetID();
    }

    /**
     * Получение конфигурации контроллера.
     * @return array Конфигурация контроллера.
     */
    protected function getConfig()
    {
        return array(
            'controllers' => array(
                'ActivateSertificate' => array(
                    'class' => __CLASS__,
                    'action' => 'ActivateSertificate',
                ),
            ),
        );
    }


    /**
     * Выполнение компонента.
     * @return void
     */
    public function executeComponent()
    {
        $action = $this->request->getPost("action");

        if ($action === "ActivateSertificate") {
            $sertCode = $this->request->getPost("sertCode");
            $this->ActivateSertificateAction($sertCode);
            die(); // Завершаем выполнение скрипта после активации сертификата
        }
        $this->getData();
        $this->includeComponentTemplate();

    }

}
