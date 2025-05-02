<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

use MDOAuth\OAuth2\Wrapper\MDirector\Factory;

class Psmdirector extends Module
{
    public function __construct()
    {
        $this->name = 'psmdirector';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Luis Guzmán';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('MDirector Sync');
        $this->description = $this->l('Sincroniza los clientes a MDirector.');
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('actionCustomerAccountAdd')
            && $this->registerHook('actionValidateOrder')
            && $this->registerHook('actionObjectCustomerUpdateAfter')
            && $this->registerHook('actionObjectCustomerDeleteBefore');
    }

    public function uninstall()
    {
        return parent::uninstall()
            && Configuration::deleteByName('PSMDIRECTOR_COMPANY_ID')
            && Configuration::deleteByName('PSMDIRECTOR_API_SECRET');
    }

    public function getContent()
    {
        if (Tools::isSubmit('submitPsmdirector')) {
            Configuration::updateValue('PSMDIRECTOR_COMPANY_ID', Tools::getValue('PSMDIRECTOR_COMPANY_ID'));
            Configuration::updateValue('PSMDIRECTOR_API_SECRET', Tools::getValue('PSMDIRECTOR_API_SECRET'));
        }

        return '
            <form method="post">
                <label for="PSMDIRECTOR_COMPANY_ID">Nombre de Usuario</label><br />
                <input type="text" name="PSMDIRECTOR_COMPANY_ID" value="' . Configuration::get('PSMDIRECTOR_COMPANY_ID') . '" /><br /><br />
                <label for="PSMDIRECTOR_API_SECRET">API Secret</label><br />
                <input type="text" name="PSMDIRECTOR_API_SECRET" value="' . Configuration::get('PSMDIRECTOR_API_SECRET') . '" /><br /><br />
                <button type="submit" name="submitPsmdirector">Guardar</button>
            </form>';
    }

    public function hookActionCustomerAccountAdd($params)
    {
        $this->enviarClienteAMDirector(new Customer($params['newCustomer']->id), 'registro');
    }

    public function hookActionValidateOrder($params)
    {
        $this->enviarClienteAMDirector(new Customer($params['customer']->id), 'compra');
    }

    public function hookActionObjectCustomerUpdateAfter($params)
    {
        if (isset($params['object']) && $params['object'] instanceof Customer) {
            $this->enviarClienteAMDirector(new Customer($params['object']->id), 'edicion');
        }
    }

    public function hookActionObjectCustomerDeleteBefore($params)
    {
        if (isset($params['object']) && $params['object'] instanceof Customer) {
            $customer = $params['object'];
            $email = $customer->email;
            $companyId = Configuration::get('PSMDIRECTOR_COMPANY_ID');
            $apiSecret = Configuration::get('PSMDIRECTOR_API_SECRET');

            if (!$companyId || !$apiSecret) {
                PrestaShopLogger::addLog('[MDirector] No se puede borrar, credenciales no configuradas.');
                return;
            }

            try {
                $client = (new Factory())->create($companyId, $apiSecret);
                $get = $client->setUri('https://api.mdirector.com/api_contact')
                    ->setMethod('get')
                    ->setParameters(['email' => $email])
                    ->request();

                $body = json_decode($get->getBody()->getContents(), true);
                if (isset($body['response']) && $body['response'] === 'ok') {
                    $conId = $body['data']['conId'] ?? null;
                    if ($conId) {
                        $client->setUri('https://api.mdirector.com/api_contact')
                            ->setMethod('delete')
                            ->setParameters([
                                'conId' => $conId,
                                'listId' => 1,
                                'reason' => 'Borrado desde PrestaShop'
                            ])
                            ->request();

                        PrestaShopLogger::addLog('[MDirector] Contacto eliminado correctamente: ' . $email);
                    }
                }
            } catch (Exception $e) {
                PrestaShopLogger::addLog('[MDirector] Error al borrar contacto: ' . $e->getMessage());
            }
        }
    }

    private function enviarClienteAMDirector(Customer $customer, string $origen = 'registro')
    {
        $email = $customer->email;
        $firstname = $customer->firstname;
        $lastname = $customer->lastname;
        $listId = 1;

        $companyId = Configuration::get('PSMDIRECTOR_COMPANY_ID');
        $apiSecret = Configuration::get('PSMDIRECTOR_API_SECRET');

        if (!$companyId || !$apiSecret) {
            PrestaShopLogger::addLog('[MDirector] Error: Credenciales no definidas.');
            return;
        }

        $addressId = Address::getFirstCustomerAddressId($customer->id);
        $addressObject = new Address($addressId);
        $mobile = $addressObject->phone_mobile ?? '';
        $postcode = $addressObject->postcode ?? '';
        $province = State::getNameById($addressObject->id_state ?? 0);
        $fechaMod = $customer->date_upd;
        $ultimaCompra = '';
        $orders = Order::getCustomerOrders((int)$customer->id, true);
        if (!empty($orders)) {
            $ultimaCompra = $orders[0]['date_add'];
        }

        try {
            $client = (new Factory())->create($companyId, $apiSecret);

            $checkResponse = $client->setUri('https://api.mdirector.com/api_contact')
                ->setMethod('get')
                ->setParameters(['email' => $email])
                ->request();

            $checkBody = json_decode($checkResponse->getBody()->getContents(), true);

            if (isset($checkBody['response']) && $checkBody['response'] === 'ok') {
                $contactId = $checkBody['data']['conId'] ?? null;

                if ($contactId) {
                    $updateParams = [
                        'listId' => $listId,
                        'conId' => $contactId,
                        'IDPrestaShop' => (string) $customer->id,
                        'Empresa' => $customer->company ?? '',
                        'Origen' => Context::getContext()->shop->name,
                        'movil' => $mobile,
                        'CP' => $postcode,
                        'Provincias' => $province,
                        'FechaModificacion' => $fechaMod,
                        'UltimaCompra' => $ultimaCompra
                    ];

                    if ($origen === 'compra') {
                        $updateParams['EsCliente'] = 'SI';
                    }

                    $client->setUri('https://api.mdirector.com/api_contact')
                        ->setMethod('put')
                        ->setParameters($updateParams)
                        ->request();

                    PrestaShopLogger::addLog('[MDirector] Cliente actualizado: ' . $email);
                }
                return;
            }
        } catch (Exception $e) {
            if (!str_contains($e->getMessage(), 'Contact not found')) {
                PrestaShopLogger::addLog('[MDirector] Error al verificar contacto: ' . $e->getMessage());
                return;
            }
        }

        $tags = ['piscihogar'];
        $freeParams = [
            'EsCliente' => $origen === 'compra' ? 'SI' : '',
            'IDPrestaShop' => (string) $customer->id,
            'Empresa' => $customer->company ?? '',
            'Origen' => Context::getContext()->shop->name,
            'CP' => $postcode,
            'Provincias' => $province,
            'FechaModificacion' => $fechaMod,
            'UltimaCompra' => $ultimaCompra
        ];

        try {
            $client->setUri('https://api.mdirector.com/api_contact')
                ->setMethod('post')
                ->setParameters([
                    'email' => $email,
                    'movil' => $mobile,
                    'name' => $firstname,
                    'surname1' => $lastname,
                    'country' => 'ES',
                    'mdTags' => $tags,
                    ...$freeParams
                ])
                ->request();

            PrestaShopLogger::addLog('[MDirector] Cliente creado: ' . $email);
        } catch (Exception $e) {
            PrestaShopLogger::addLog('[MDirector] Error al crear contacto: ' . $e->getMessage());
        }
    }
}