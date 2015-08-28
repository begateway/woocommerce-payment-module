## WooCommerce payment module

[Русская версия](#Модуль-оплаты-woocommerce)

### Installation

  * Backup your webstore and database
  * Download [woocommerce-begateway.zip](https://github.com/beGateway/woocommerce-payment-module/raw/woocommerce-begateway.zip)
  * Start up the administrative panel for Wordpress (www.yourshop.com/wp-admin/)
  * Choose _Plugins -> Add New_
  * Upload the payment module archive via **Upload Plugin**.
  * Choose _Plugins -> Installed Plugins_ and find the _WooCommerce beGateway Payment Gateway_ plugin and activate it.

![Activate](https://github.com/beGateway/woocommerce-payment-module/raw/master/doc/activate-plugin-en.png)

### Setup

Now go to _WooCommerce -> Settings -> Checkout_

![Setup-1](https://github.com/beGateway/woocommerce-payment-module/raw/master/doc/setup-plugin-1-en.png)

At the top of the page you will see a link entitled `beGateway` – click on that to bring up the setup page.
This will bring up a page displaying all the options that you can select to administer the payment module – these are all fairly self-explanatory.

![Setup-2](https://github.com/beGateway/woocommerce-payment-module/raw/master/doc/setup-plugin-2-en.png)

  * set _Title_ e.g. _Credit or debit card_
  * set _Admin Title_ e.g. _beGateway_
  * set _Description_ e.g. _VISA, MasterCard_. You are free to put all payment cards supported by your acquiring payment agreement.
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

values received from your payment processor.

  * click _Save changes_

Now the module is configured.

### Notes

Tested and developed with:

  * Wordress 4.2.x / 4.3.x
  * WooCommerce 2.3.x / 2.4.x

PHP 5.3+ is required.

### Demo credentials

You are free to use the settings to configure the module to process
payments with a demo gateway.

  * Shop Id __361__
  * Shop secret key __b8647b68898b084b836474ed8d61ffe117c9a01168d867f24953b776ddcb134d__
  * Payment gateway domain __demo-gateway.begateway.com__
  * Payment page domain __checkout.begateway.com__

Use the test data to make a test payment:

  * card number __4200000000000000__
  * card name __John Doe__
  * card expiry month __01__ to get a success payment
  * card expiry month __10__ to get a failed payment
  * CVC __123__

### Contributing

Issue pull requests or send feature requests.

## Модуль оплаты WooCommerce

[English version](#woocommerce-payment-module)

### Установка

  * Создайте резервную копию вашего магазина и базы данных
  * Загрузите [woocommerce-begateway.zip](https://github.com/beGateway/woocommerce-payment-module/raw/woocommerce-begateway.zip)
  * Зайдите в панель администратора Wordpress (www.yourshop.com/wp-admin/)
  * Выберите _Плагины -> Добавить новый_
  * Загрузите модуль через **Добавить новый**
  * Выберите _Плагины -> Установленные_ и найдите _WooCommerce beGateway Payment Gateway_ модуль и активируйте его.

![Activate](https://github.com/beGateway/woocommerce-payment-module/raw/master/doc/activate-plugin-ru.png)

### Настройка

Зайдите в _WooCommerce -> Настройки -> Оплата_

![Setup-1](https://github.com/beGateway/woocommerce-payment-module/raw/master/doc/setup-plugin-1-ru.png)

Вверху страницы вы увидите ссылку `beGateway`. Нажмите на ее и откроется
страницы настройки модуля.

Параметры понятные и говорят сами за себя.

![Setup-2](https://github.com/beGateway/woocommerce-payment-module/raw/master/doc/setup-plugin-2-ru.png)

  * задайте _Заголовок_ e.g. _Credit or debit card_
  * задайте _Заголовок для администратора_ e.g. _beGateway_
  * задайте _Описание_ e.g. _VISA, MasterCard_. You are free to put all payment cards supported by your acquiring payment agreement.
  * задайте _Тип транзакции_: _Авторизация_ или _Платеж_
  * отметьте _Включить администратору возможность списания/отмены авторизации/возврат_ если хотите посылать списания/возвраты/отмену авторизации из панели администратора WooCommerce
  * отметьте _Журнал отладки_ если хотите журналировать события модуля

В следующих полях:

  * _Id магазина_
  * _Секретный ключ_
  * _Домен платежного шлюза_
  * _Домен страницы оплаты_

введите значения, полученные от вашей платежной компании.

  * нажмите _Сохранить изменения_

Модуль настроен и готов к работе.

### Примечания

Разработанно и протестированно с:

  * Wordress 4.2.x / 4.3.x
  * WooCommerce 2.3.x / 2.4.x

Требуется PHP 5.3+

### Тестовые данные

Вы можете использовать следующие данные, чтобы настроить способ оплаты в
тестовом режиме:

  * Идентификационный номер магазина __361__
  * Секретный ключ магазина __b8647b68898b084b836474ed8d61ffe117c9a01168d867f24953b776ddcb134d__
  * Домен платежного шлюза __demo-gateway.begateway.com__
  * Домен платежной страницы __checkout.begateway.com__

Используйте следующий тестовый набор для тестового платежа:

  * номер карты __4200000000000000__
  * имя на карте __John Doe__
  * месяц срока действия карты __01__, чтобы получить успешный платеж
  * месяц срока действия карты __10__, чтобы получить неуспешный платеж
  * CVC __123__
