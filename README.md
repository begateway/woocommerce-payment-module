# WooCommerce BeGateway Payment Gateway

It supports [WooCommerce™ Subscriptions](https://woocommerce.com/products/woocommerce-subscriptions/)

[Русская версия](#Модуль-оплаты-woocommerce)

## Installation

  * Backup your webstore and database
  * Download [the latest payment plugin](https://github.com/begateway/woocommerce-payment-module/releases)
  * Start up the administrative panel for Wordpress (www.yourshop.com/wp-admin/)
  * Choose _Plugins → Add New_
  * Upload the payment module archive via **Upload Plugin**.
  * Choose _Plugins → Installed Plugins_ and find the _WooCommerce BeGateway Payment Gateway_ plugin and activate it.

![Activate](https://github.com/beGateway/woocommerce-payment-module/raw/master/doc/activate-plugin-en.png)

## Setup

Now go to _WooCommerce → Settings → Checkout_

![Setup-1](https://github.com/beGateway/woocommerce-payment-module/raw/master/doc/setup-plugin-1-en.png)

At the top of the page you will see a link entitled `BeGateway` – click on that to bring up the setup page.
This will bring up a page displaying all the options that you can select to administer the payment module – these are all fairly self-explanatory.

![Setup-2](https://github.com/beGateway/woocommerce-payment-module/raw/master/doc/setup-plugin-2-en.png)

  * set _Title_ e.g. _Credit or debit card_
  * set _Admin Title_ e.g. _beGateway_
  * set _Description_ e.g. _Visa, Mastercard_. You are free to put all payment cards supported by your acquiring payment agreement.
  * Transaction type: _Authorization_ or _Payment_
  * Check _Enable admin capture etc_ if you want to allow administrators
    to issue refunds or captures from WooCommerce backend
  * Check _Debug Log_ if you want to log messages between _beGateway_
    and WooCommerce

Enter in fields as follows:

  * _Shop Id_
  * _Shop Key_
  * _Payment gateway domain_
  * _Payment page domain_
  * and etc

values received from your payment processor.

  * click _Save changes_

Now the module is configured.

## Notes

Tested and developed with:

  * WooCommerce 7.x/8.x
  * PHP 7.x/8.x

## Testing

You can use the following information to adjust the payment method in test mode:

  * __Shop ID:__ 361
  * __Shop Key:__ b8647b68898b084b836474ed8d61ffe117c9a01168d867f24953b776ddcb134d
  * __Checkout page domain:__ checkout.begateway.com

Use the following test card to make successful test payment:

  * Card number: 4200000000000000
  * Name on card: JOHN DOE
  * Card expiry date: 01/30
  * CVC: 123

Use the following test card to make failed test payment:

  * Card number: 4005550000000019
  * Name on card: JOHN DOE
  * Card expiry date: 01/30
  * CVC: 123

Use [the guide](https://docs.woocommerce.com/document/testing-subscription-renewal-payments/) to test subscription renewal payments.

# Плагин оплаты WooCommerce beGateway Payment Gateway

Модуль поддерживает работу с плагином подписок [WooCommerce™ Subscriptions](https://woocommerce.com/products/woocommerce-subscriptions/)

[English version](#woocommerce-payment-module)

## Установка

  * Создайте резервную копию вашего магазина и базы данных
  * Загрузите [последнию версию плагина оплаты](https://github.com/begateway/woocommerce-payment-module/releases)
  * Зайдите в панель администратора Wordpress (www.yourshop.com/wp-admin/)
  * Выберите _Плагины → Добавить новый_
  * Загрузите модуль через **Добавить новый**
  * Выберите _Плагины → Установленные_ и найдите _WooCommerce BeGateway Payment Gateway_ модуль и активируйте его.

![Activate](https://github.com/beGateway/woocommerce-payment-module/raw/master/doc/activate-plugin-ru.png)

## Настройка

Зайдите в _WooCommerce → Настройки → Оплата_

![Setup-1](https://github.com/beGateway/woocommerce-payment-module/raw/master/doc/setup-plugin-1-ru.png)

Вверху страницы вы увидите ссылку `BeGateway`. Нажмите на ее и откроется
страницы настройки модуля.

Параметры понятные и говорят сами за себя.

![Setup-2](https://github.com/beGateway/woocommerce-payment-module/raw/master/doc/setup-plugin-2-ru.png)

  * задайте _Заголовок_ e.g. _Credit or debit card_
  * задайте _Заголовок для администратора_ e.g. _beGateway_
  * задайте _Описание_ e.g. _Visa, Mastercard_. You are free to put all payment cards supported by your acquiring payment agreement.
  * задайте _Тип транзакции_: _Авторизация_ или _Платеж_
  * отметьте _Включить администратору возможность списания/отмены авторизации/возврат_ если хотите посылать списания/возвраты/отмену авторизации из панели администратора WooCommerce
  * отметьте _Журнал отладки_ если хотите журналировать события модуля

В следующих полях:

  * _Id магазина_
  * _Секретный ключ_
  * _Домен платежного шлюза_
  * _Домен страницы оплаты_
  * и т.д.

введите значения, полученные от вашей платежной компании.

  * нажмите _Сохранить изменения_

Модуль настроен и готов к работе.

## Примечания

Разработанно и протестированно с:

  * WooCommerce 7.x/8.x
  * PHP 7.x/8.x

## Тестирование

Вы можете использовать следующие данные, чтобы настроить способ оплаты в тестовом режиме

  * __Идентификационный номер магазина:__ 361
  * __Секретный ключ магазина:__ b8647b68898b084b836474ed8d61ffe117c9a01168d867f24953b776ddcb134d
  * __Домен платежного шлюза:__ demo-gateway.begateway.com
  * __Домен платежной страницы:__ checkout.begateway.com
  * __Режим работы:__ Тестовый

Используйте следующие данные карты для успешного тестового платежа:

  * Номер карты: 4200000000000000
  * Имя на карте: JOHN DOE
  * Месяц срока действия карты: 01/30
  * CVC: 123

Используйте следующие данные карты для неуспешного тестового платежа:

  * Номер карты: 4005550000000019
  * Имя на карте: JOHN DOE
  * Месяц срока действия карты: 01/30
  * CVC: 123

Для тестирования продления подписок следуйте [инструкции](https://docs.woocommerce.com/document/testing-subscription-renewal-payments/)
