# WooCommerce BeGateway Payment Gateway

It supports [WooCommerce™ Subscriptions](https://woocommerce.com/products/woocommerce-subscriptions/)

[Русская версия](#Модуль-оплаты-woocommerce)

## Installation

  * Back up your webstore and database
  * Download [the latest payment plugin](https://github.com/begateway/woocommerce-payment-module/releases)
  * Start up the administrative panel for WordPress (www.yourshop.com/wp-admin/)
  * Choose _Plugins → Add New_
  * Upload the payment module archive via **Upload Plugin**
  * Choose _Plugins → Installed Plugins_ and find the _BeGateway Payment Gateway for WooCommerce_ plugin and activate it

![Activate](https://github.com/beGateway/woocommerce-payment-module/raw/master/doc/activate-plugin-en.png)

## Setup

Now go to _WooCommerce → Settings → Payments_

![Setup-1](https://github.com/beGateway/woocommerce-payment-module/raw/master/doc/setup-plugin-1-en.png)

At the top of the page you will see a link entitled `BeGateway` — click on it to bring up the setup page.
This will bring up a page displaying all the options that you can select to configure the payment module — these are all fairly self-explanatory.

![Setup-2](https://github.com/beGateway/woocommerce-payment-module/raw/master/doc/setup-plugin-2-en.png)

  * set _Title_ e.g. _Credit or debit card_
  * set _Description_ e.g. _Visa, Mastercard_. You are free to put all payment cards supported by your acquiring payment agreement
  * select _Transaction type_: _Authorization_ or _Payment_
  * select _Payment methods_: _Bankcard_, _Halva_, _ERIP_
  * check _Debug Log_ if you want to log messages between _BeGateway_ and WooCommerce

Enter in the following fields:

  * _Shop ID_
  * _Secret key_
  * _Public key_
  * _Payment gateway domain_
  * _Payment page domain_

values received from your payment processor.

  * click _Save changes_

Now the module is configured.

## Managing transactions

When the transaction type is set to _Authorization_, payments are not captured automatically. You can manage transactions directly from the WooCommerce order page. Open an order paid via BeGateway, and you will see the _Transactions_ panel on the right side of the order page.

### Capture

For authorized (not yet captured) orders, the _Transactions_ panel displays the authorized amount and provides options to capture the full amount or a partial amount.

![Transactions — Authorization](https://github.com/beGateway/woocommerce-payment-module/raw/master/doc/transactions-metabox-en.png)

### Refund

After a payment has been captured, you can issue a full or partial refund from the same _Transactions_ panel.

![Transactions — Refund](https://github.com/beGateway/woocommerce-payment-module/raw/master/doc/transactions-metabox-captured-en.png)

### Cancel

For authorized orders that have not been captured yet, you can cancel (void) the authorization using the _Cancel transaction_ button.

## Notes

Tested and developed with:

  * WooCommerce 7.x/8.x/9.x/10.x
  * PHP 7.x/8.x

## Testing

You can use the following information to adjust the payment method in test mode:

  * __Shop ID:__ 361
  * __Secret key:__ b8647b68898b084b836474ed8d61ffe117c9a01168d867f24953b776ddcb134d
  * __Payment page domain:__ checkout.begateway.com
  * __Payment mode:__ Test

Use the following test card to make a successful test payment:

  * Card number: 4200000000000000
  * Name on card: JOHN DOE
  * Card expiry date: 01/30
  * CVC: 123

Use the following test card to make a failed test payment:

  * Card number: 4005550000000019
  * Name on card: JOHN DOE
  * Card expiry date: 01/30
  * CVC: 123

Use [the guide](https://docs.woocommerce.com/document/testing-subscription-renewal-payments/) to test subscription renewal payments.

# Модуль оплаты WooCommerce BeGateway Payment Gateway

Модуль поддерживает работу с плагином подписок [WooCommerce™ Subscriptions](https://woocommerce.com/products/woocommerce-subscriptions/)

[English version](#woocommerce-begateway-payment-gateway)

## Установка

  * Создайте резервную копию вашего магазина и базы данных
  * Загрузите [последнюю версию модуля оплаты](https://github.com/begateway/woocommerce-payment-module/releases)
  * Зайдите в панель администратора WordPress (www.yourshop.com/wp-admin/)
  * Выберите _Плагины → Добавить новый_
  * Загрузите архив модуля через **Загрузить плагин**
  * Выберите _Плагины → Установленные_ и найдите модуль _Платёжное решение BeGateway для WooCommerce_, затем активируйте его

![Activate](https://github.com/beGateway/woocommerce-payment-module/raw/master/doc/activate-plugin-ru.png)

## Настройка

Зайдите в _WooCommerce → Настройки → Платежи_

![Setup-1](https://github.com/beGateway/woocommerce-payment-module/raw/master/doc/setup-plugin-1-ru.png)

Вверху страницы вы увидите ссылку `BeGateway`. Нажмите на неё, и откроется страница настройки модуля.

Параметры понятны и говорят сами за себя.

![Setup-2](https://github.com/beGateway/woocommerce-payment-module/raw/master/doc/setup-plugin-2-ru.png)

  * задайте _Заголовок_, например _Банковская карта_
  * задайте _Описание_, например _Visa, Mastercard_. Вы можете указать все платёжные карты, поддерживаемые вашим эквайринговым договором
  * выберите _Тип операции_: _Авторизация_ или _Оплата_
  * выберите _Способы оплаты_: _Банковская карта_, _Халва_, _ЕРИП_
  * отметьте _Журнал отладки_, если хотите журналировать события модуля

В следующих полях:

  * _ID магазина_
  * _Секретный ключ_
  * _Публичный ключ_
  * _Домен платёжного шлюза_
  * _Домен страницы оплаты_

введите значения, полученные от вашей платёжной компании.

  * нажмите _Сохранить изменения_

Модуль настроен и готов к работе.

## Управление операциями

Если тип операции установлен в значение _Авторизация_, платежи не списываются автоматически. Управлять операциями можно непосредственно со страницы заказа WooCommerce. Откройте заказ, оплаченный через BeGateway, и в правой части страницы вы увидите панель _Операции_.

### Списание

Для авторизованных (ещё не списанных) заказов панель _Операции_ отображает сумму авторизации и предоставляет возможность списать полную сумму или произвести частичное списание.

![Операции — Авторизация](https://github.com/beGateway/woocommerce-payment-module/raw/master/doc/transactions-metabox-ru.png)

### Возврат

После списания средств вы можете оформить полный или частичный возврат из той же панели _Операции_.

![Операции — Возврат](https://github.com/beGateway/woocommerce-payment-module/raw/master/doc/transactions-metabox-captured-ru.png)

### Отмена авторизации

Для авторизованных заказов, по которым ещё не было произведено списание, вы можете отменить авторизацию, нажав кнопку _Отменить операцию_.

## Примечания

Разработано и протестировано с:

  * WooCommerce 7.x/8.x/9.x/10.x
  * PHP 7.x/8.x

## Тестирование

Вы можете использовать следующие данные, чтобы настроить способ оплаты в тестовом режиме:

  * __ID магазина:__ 361
  * __Секретный ключ:__ b8647b68898b084b836474ed8d61ffe117c9a01168d867f24953b776ddcb134d
  * __Домен платёжной страницы:__ checkout.begateway.com
  * __Режим работы:__ Тестовый

Используйте следующие данные карты для успешного тестового платежа:

  * Номер карты: 4200000000000000
  * Имя на карте: JOHN DOE
  * Срок действия карты: 01/30
  * CVC: 123

Используйте следующие данные карты для неуспешного тестового платежа:

  * Номер карты: 4005550000000019
  * Имя на карте: JOHN DOE
  * Срок действия карты: 01/30
  * CVC: 123

Для тестирования продления подписок следуйте [инструкции](https://docs.woocommerce.com/document/testing-subscription-renewal-payments/).
