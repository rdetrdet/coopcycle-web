<?php

namespace AppBundle\Service;

use AppBundle\Payment\GatewayResolver;
use AppBundle\Utils\Settings;
use Craue\ConfigBundle\Util\Config as CraueConfig;
use Craue\ConfigBundle\CacheAdapter\CacheAdapterInterface as CraueCache;
use Doctrine\Persistence\ManagerRegistry;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;
use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

class SettingsManager
{
    private $craueConfig;
    private $craueCache;
    private $configEntityName;
    private $phoneNumberUtil;
    private $country;
    private $foodtechEnabled;
    private $b2bEnabled;
    private $doctrine;
    private $gatewayResolver;
    private $projectDir;
    private $forceStripe;

    private $secretSettings = [
        'stripe_test_publishable_key',
        'stripe_test_secret_key',
        'stripe_test_connect_client_id',
        'stripe_live_publishable_key',
        'stripe_live_secret_key',
        'stripe_live_connect_client_id',
        'payment_gateway',
        'payment_method_publishable_key',
        'mercadopago_test_publishable_key',
        'mercadopago_live_publishable_key',
        'mercadopago_test_access_token',
        'mercadopago_live_access_token',
        'mercadopago_client_secret',
        'google_api_key_custom',
        'paygreen_public_key',
        'paygreen_secret_key',
    ];

    private static $boolean = [
        'sms_enabled',
        'subject_to_vat',
        'guest_checkout_enabled',
    ];

    private $cache = [];

    public function __construct(
        CraueConfig $craueConfig,
        CraueCache $craueCache,
        string $configEntityName,
        ManagerRegistry $doctrine,
        PhoneNumberUtil $phoneNumberUtil,
        string $country,
        bool $foodtechEnabled,
        bool $b2bEnabled,
        GatewayResolver $gatewayResolver,
        string $projectDir,
        $forceStripe = false)
    {
        $this->craueConfig = $craueConfig;
        $this->craueCache = $craueCache;
        $this->configEntityName = $configEntityName;
        $this->doctrine = $doctrine;
        $this->phoneNumberUtil = $phoneNumberUtil;
        $this->country = $country;
        $this->foodtechEnabled = $foodtechEnabled;
        $this->b2bEnabled = $b2bEnabled;
        $this->gatewayResolver = $gatewayResolver;
        $this->projectDir = $projectDir;
        $this->forceStripe = $forceStripe;
    }

    public function isSecret($name)
    {
        return in_array($name, $this->secretSettings);
    }

    public function get($name)
    {
        switch ($name) {
            case 'payment_gateway':
                return $this->gatewayResolver->resolve();
            case 'stripe_publishable_key':
                $name = $this->isStripeLivemode() ? 'stripe_live_publishable_key' : 'stripe_test_publishable_key';
                break;
            case 'stripe_secret_key':
                $name = $this->isStripeLivemode() ? 'stripe_live_secret_key' : 'stripe_test_secret_key';
                break;
            case 'stripe_connect_client_id':
                $name = $this->isStripeLivemode() ? 'stripe_live_connect_client_id' : 'stripe_test_connect_client_id';
                break;
            case 'stripe_webhook_secret':
                $name = $this->isStripeLivemode() ? 'stripe_live_webhook_secret' : 'stripe_test_webhook_secret';
                break;
            case 'mercadopago_publishable_key':
                $name = $this->isMercadopagoLivemode() ? 'mercadopago_live_publishable_key' : 'mercadopago_test_publishable_key';
                break;
            case 'mercadopago_access_token':
                $name = $this->isMercadopagoLivemode() ? 'mercadopago_live_access_token' : 'mercadopago_test_access_token';
                break;
            case 'mercadopago_access_token_for_test':
                // to be used only for test
                $name = !$this->isMercadopagoLivemode() ? 'mercadopago_live_access_token' : '';
                break;
            case 'timezone':
                return ini_get('date.timezone');
            case 'foodtech_enabled':
                return $this->foodtechEnabled;
            case 'b2b_enabled':
                return $this->b2bEnabled;
        }

        if (isset($this->cache[$name])) {

            return $this->cache[$name];
        }

        try {

            $value = $this->craueConfig->get($name);

            switch ($name) {
                case 'phone_number':
                    try {
                        $value = $this->phoneNumberUtil->parse($value, strtoupper($this->country));
                    } catch (NumberParseException $e) {}
                    break;
            }

            if (in_array($name, self::$boolean)) {
                $value = (bool) $value;
            }

            $this->cache[$name] = $value;

            return $value;

        } catch (\RuntimeException $e) {}
    }

    public function getBoolean($name)
    {
        return filter_var($this->get($name), FILTER_VALIDATE_BOOLEAN);
    }

    public function isStripeLivemode()
    {
        $livemode = $this->get('stripe_livemode');

        if (!$livemode) {
            return false;
        }

        return filter_var($livemode, FILTER_VALIDATE_BOOLEAN);
    }

    public function isMercadopagoLivemode()
    {
        $livemode = $this->get('mercadopago_livemode');

        if (!$livemode) {
            return false;
        }

        return filter_var($livemode, FILTER_VALIDATE_BOOLEAN);
    }

    public function canEnableStripeTestmode()
    {
        try {
            $stripeTestPublishableKey = $this->craueConfig->get('stripe_test_publishable_key');
            $stripeTestSecretKey = $this->craueConfig->get('stripe_test_secret_key');
            $stripeTestConnectClientId = $this->craueConfig->get('stripe_test_connect_client_id');

            return !empty($stripeTestPublishableKey) && !empty($stripeTestSecretKey) && !empty($stripeTestConnectClientId);

        } catch (\RuntimeException $e) {
            return false;
        }
    }

    public function canEnableStripeLivemode()
    {
        try {
            $stripeLivePublishableKey = $this->craueConfig->get('stripe_live_publishable_key');
            $stripeLiveSecretKey = $this->craueConfig->get('stripe_live_secret_key');
            $stripeLiveConnectClientId = $this->craueConfig->get('stripe_live_connect_client_id');

            return !empty($stripeLivePublishableKey) && !empty($stripeLiveSecretKey) && !empty($stripeLiveConnectClientId);

        } catch (\RuntimeException $e) {
            return false;
        }
    }

    public function canEnableMercadopagoTestmode()
    {
        try {
            $mercadopagoTestPublishableKey = $this->craueConfig->get('mercadopago_test_publishable_key');
            $mercadopagoTestSecretKey = $this->craueConfig->get('mercadopago_test_access_token');

            return !empty($mercadopagoTestPublishableKey) && !empty($mercadopagoTestSecretKey);

        } catch (\RuntimeException $e) {
            return false;
        }
    }

    public function canEnableMercadopagoLivemode()
    {
        try {
            $mercadopagoLivePublishableKey = $this->craueConfig->get('mercadopago_live_publishable_key');
            $mercadopagoLiveSecretKey = $this->craueConfig->get('mercadopago_live_access_token');

            return !empty($mercadopagoLivePublishableKey) && !empty($mercadopagoLiveSecretKey);

        } catch (\RuntimeException $e) {
            return false;
        }
    }

    public function supportsCardPayments()
    {
        $supportsStripe = $this->canEnableStripeTestmode() || $this->canEnableStripeLivemode();
        $supportsMercadopago = $this->canEnableMercadopagoTestmode() || $this->canEnableMercadopagoLivemode();
        $supportsPaygreen = $this->configKeysAreNotEmpty('paygreen_public_key', 'paygreen_secret_key', 'paygreen_shop_id');

        if ($this->forceStripe) {

            return $supportsStripe;
        }

        return $supportsStripe || $supportsMercadopago || $supportsPaygreen;
    }

    public function canSendSms()
    {
        if (!$this->get('sms_enabled')) {

            return false;
        }

        $smsGateway = $this->get('sms_gateway');

        if (!$smsGateway || !in_array($smsGateway, ['mailjet', 'twilio'])) {

            return false;
        }

        $smsGatewayConfig = $this->get('sms_gateway_config');

        if (empty($smsGatewayConfig)) {

            return false;
        }

        $smsGatewayConfig = json_decode($smsGatewayConfig, true);

        if (empty($smsGatewayConfig)) {

            return false;
        }

        switch ($smsGateway) {
            case 'mailjet':
                return in_array($this->country, ['be', 'es', 'de', 'fr'])
                    && isset($smsGatewayConfig['api_token']);
            case 'twilio':
                return isset(
                    $smsGatewayConfig['sid'],
                    $smsGatewayConfig['auth_token'],
                    $smsGatewayConfig['from']
                );
        }

        return false;
    }

    public function set($name, $value)
    {
        try {

            $this->craueConfig->set($name, $value);

        } catch (\RuntimeException $e) {

            $className = $this->configEntityName;

            $entityManager = $this->doctrine
                ->getManagerForClass($className);

            // Create the setting if it does not exist
            $setting = new $className();
            $setting->setName($name);
            $setting->setValue($value);

            // Avoid flushing changes for all objects
            $entityManager->persist($setting);
            $entityManager->getUnitOfWork()->commit($setting);

            $this->craueConfig->set($name, $value);
        }
    }

    public function flush()
    {
        $this->doctrine->getManagerForClass($this->configEntityName)->flush();
        $this->craueCache->clear();
    }

    public function asEntity()
    {
        $settings = new Settings();

        $keys = array_keys(get_object_vars($settings));

        foreach ($keys as $name) {
            try {
                $value = $this->craueConfig->get($name);

                if (in_array($name, self::$boolean)) {
                    $value = (bool) $value;
                }

                $settings->$name = $value;
            } catch (\RuntimeException $e) {}
        }

        return $settings;
    }

    public function clearCache()
    {
        $this->craueCache->clear();
    }

    public function delete($name)
    {
        $setting = $this->doctrine->getRepository($this->configEntityName)->findOneBy([
            'name' => $name,
        ]);

        if ($setting !== null) {
            $this->doctrine->getManagerForClass($this->configEntityName)->remove($setting);
        }
    }

    private function configKeysAreNotEmpty(...$keys)
    {
        foreach ($keys as $key) {
            try {
                $value = $this->craueConfig->get($key);
                if (empty($value)) {
                    return false;
                }
            } catch (\RuntimeException $e) {
                return false;
            }
        }

        return true;
    }

    public function getVersion(): string
    {
        // TODO Add caching

        // If there is a REVISION file in project dir containing a SHA1, trust it
        $revisionFile = $this->projectDir . '/REVISION';
        if (file_exists($revisionFile)) {

            $sha1 = trim(file_get_contents($revisionFile));

            $process = new Process(['git', 'ls-remote', '--tags', 'https://github.com/coopcycle/coopcycle-web.git']);
            $process->run();

            if ($process->isSuccessful()) {
                $lines = explode("\n", $process->getOutput());
                foreach ($lines as $line) {
                    $pattern = sprintf('#^%s[ \t]+refs\/tags\/(v[0-9\.]+)$#', $sha1);
                    if (1 === preg_match($pattern, $line, $matches)) {
                        return trim($matches[1]);
                    }
                }
            }
        }

        // https://stackoverflow.com/questions/2324195/how-to-get-tags-on-current-commit
        $process = new Process(['git', 'tag', '--points-at', 'HEAD'], $this->projectDir);
        $process->run();

        if (!$process->isSuccessful()) {
            return 'dev-master';
        }

        $version = trim($process->getOutput());

        if (empty($version)) {
            return 'dev-master';
        }

        return $version;
    }
}
