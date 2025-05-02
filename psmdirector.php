<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

use MDOAuth\OAuth2\Wrapper\MDirector\Factory;
use Dotenv\Dotenv;

class Psmdirector extends Module
{
    public function __construct()
    {
        $dotenv = Dotenv::createImmutable(__DIR__);
        $dotenv->safeLoad();

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
            && $this->registerHook('actionValidateOrder');
    }

    public function hookActionCustomerAccountAdd($params)
    {
        PrestaShopLogger::addLog('[MDirector] Registro de cliente detectado.');
        $this->enviarClienteAMDirector(new Customer($params['newCustomer']->id), true);
    }

    public function hookActionValidateOrder($params)
    {
        PrestaShopLogger::addLog('[MDirector] Compra detectada, validando cliente.');
        $this->enviarClienteAMDirector(new Customer($params['customer']->id), false);
    }

    private function enviarClienteAMDirector(Customer $customer, bool $soloRegistro = false)
    {
        $email = $customer->email;
        $firstname = $customer->firstname;
        $lastname = $customer->lastname;
        $listId = 1;

        $companyId = $_ENV['MDIRECTOR_COMPANY_ID'] ?? null;
        $apiSecret = $_ENV['MDIRECTOR_API_SECRET'] ?? null;

        if (!$companyId || !$apiSecret) {
            PrestaShopLogger::addLog('[MDirector] Error: Variables de entorno no definidas.');
            return;
        }

        try {
            $client = (new Factory())->create($companyId, $apiSecret);

            // Verificar si ya existe
            $checkResponse = $client->setUri('https://api.mdirector.com/api_contact')
                ->setMethod('get')
                ->setParameters(['email' => $email])
                ->request();

            $checkBody = json_decode($checkResponse->getBody()->getContents(), true);

            if (isset($checkBody['response']) && $checkBody['response'] === 'ok') {
                PrestaShopLogger::addLog('[MDirector] Cliente ya existe: ' . $email);

                // Si es una compra, actualizamos EsCliente = "SI"
                if (!$soloRegistro) {
                    try {
                        $contactId = $checkBody['data']['conId'] ?? null;
                        if ($contactId) {
                            $putResponse = $client->setUri('https://api.mdirector.com/api_contact')
                                ->setMethod('put')
                                ->setParameters([
                                    'listId' => $listId,
                                    'conId' => $contactId,
                                    'EsCliente' => 'SI'
                                ])
                                ->request();

                            $putBody = json_decode($putResponse->getBody()->getContents(), true);

                            if (isset($putBody['response']) && $putBody['response'] === 'ok') {
                                PrestaShopLogger::addLog('[MDirector] Cliente actualizado como comprador: ' . $email);
                            } else {
                                PrestaShopLogger::addLog('[MDirector] Error actualizando EsCliente: ' . json_encode($putBody));
                            }
                        }
                    } catch (Exception $e) {
                        PrestaShopLogger::addLog('[MDirector] Excepción actualizando cliente: ' . $e->getMessage());
                    }
                }

                return;
            }
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Contact not found') === false) {
                PrestaShopLogger::addLog('[MDirector] Error al verificar existencia: ' . $e->getMessage());
                return;
            }
        }

        // Contacto nuevo
        $tags = ['piscihogar'];
        $freeParams = [
            'EsCliente' => $soloRegistro ? '' : 'SI',
            'IDPrestaShop' => (string) $customer->id,
            'Empresa' => isset($customer->company) ? $customer->company : '',
            'Origen' => Context::getContext()->shop->name
        ];

        try {
            $response = $client->setUri('https://api.mdirector.com/api_contact')
                ->setMethod('post')
                ->setParameters([
                    'email' => $email,
                    'name' => $firstname,
                    'surname1' => $lastname,
                    'country' => 'ES',
                    'mdTags' => $tags,
                    ...$freeParams
                ])
                ->request();

            $body = json_decode($response->getBody()->getContents(), true);

            if (isset($body['response']) && $body['response'] === 'ok') {
                PrestaShopLogger::addLog('[MDirector] Cliente creado: ' . $email);
            } else {
                PrestaShopLogger::addLog('[MDirector] Error al crear cliente: ' . json_encode($body));
            }
        } catch (Exception $e) {
            PrestaShopLogger::addLog('[MDirector] Excepción al crear contacto: ' . $e->getMessage());
        }
    }
}