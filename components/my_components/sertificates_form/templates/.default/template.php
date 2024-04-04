<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true)
    die();

$userID = $USER->GetID(); // Получаем текущего пользователя
$sertificatesList = $arResult["ELEMENTS"];

?>
<?php
use Bitrix\Main\Page\Asset;

Asset::getInstance()->addCss(__DIR__ . "/styles.css");
?>


<?php if ($userID <= 0): ?>
    <div class="false-avtorization">
        <p>Для активации сертификата зарегистрируйтесь</p>
    </div>
    <?php die(); ?>
<?php endif; ?>

<section class="sertificates-component" id="sertList">
    <form class="sertificates-component__form sertificates-form">
        <label for="sertificate-number">Номер сертификата: <input type="text" name="sertCode"
                id="sertificate-number"></label>
        <button hx-target="#sertList"
            hx-post="/bitrix/services/main/ajax.php?c=my_components:sertificates_form&action=ActivateSertificate&mode=class"
            class="sertificates-form__submit-btn">
            <p>Активировать</p>
        </button>
        <?php if ($arResult["status"]): ?>
            <p>
                <?= $arResult["status"] ?>
            </p>
        <?php endif; ?>
        <?php if ($arResult["SertPath"]): ?>
            <a href="<?= $arResult["SertPath"] ?>" download>
                Скачать сертификат
            </a>
        <?php endif; ?>
    </form>

    <?php if ($sertificatesList): ?>
        <div class="sertificates-component__list sertificates-list">
            <h3 class="sertificates-list___title">
                Список использованных сертификатов
            </h3>
            <div class="sertificates-list__elems">
                <? foreach ($sertificatesList as $sertificate): ?>
                    <div class="sertificates-list__elem">
                        <div class="number">
                            <?= $sertificate["CODE"] ?>
                        </div>
                        <div class="time">
                            <?= $sertificate["PROPERTY_ACTIVITY_DATE_VALUE"] ?>
                        </div>
                        <div class="title">
                            <?= $sertificate["NAME"] ?>
                        </div>
                    </div>
                <? endforeach ?>
            </div>
        </div>
    <? else: ?>
        <div class="not-sertificates">
            <p>У вас нет использованных сертификатов</p>
        </div>
    <?php endif; ?>
</section>