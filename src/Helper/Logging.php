<?php

namespace SilverStripe\Omnipay\Helper;

use Psr\Container\NotFoundExceptionInterface;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;

class Logging
{
    use Configurable;

    const LOGSTYLE_VERBOSE = 'verbose';
    const LOGSTYLE_SIMPLE = 'simple';
    const LOGSTYLE_FULL = 'full';

    /**
     * The Gateway-Data logging style. Can be one of the following:
     *  - 'full': Verbose logging, log all information. This will automatically turn into 'verbose' on a live environment!
     *  - 'verbose': Verbose logging, but strips out sensitive information
     *  - 'simple': Simplified messages
     * @var string
     * @config
     */
    private static $logStyle = 'verbose';

    /**
     * Field-Names that should be removed from the log
     * @var array
     * @config
     */
    private static $loggingBlacklist = [
        'card', 'token', 'cvv'
    ];

    /**
     * Get a logger
     * @return \Psr\Log\LoggerInterface
     */
    public static function getLogger()
    {
        $logger = null;
        try {
            $logger = Injector::inst()->get('SilverStripe\Omnipay\Logger');
        } catch (NotFoundExceptionInterface $e) {
            /* no op */
        }
        return $logger;
    }

    /**
     * Prepare data for logging by cleaning up the data or simplify it.
     * @param mixed $data the incoming data to log
     * @return array processed data for logging
     */
    public static function prepareForLogging($data)
    {
        if (empty($data)) {
            return [];
        }

        // If not an array, wrap it as an array
        if (!is_array($data)) {
            return [$data];
        }

        if (self::config()->logStyle == self::LOGSTYLE_SIMPLE) {
            return array_filter([
                isset($data['Message']) ? $data['Message'] : null,
                isset($data['Code']) ? $data['Code'] : null
            ]);
        }

        if (Director::isLive() || self::config()->logStyle == self::LOGSTYLE_VERBOSE) {
            self::sanitize($data);
        }

        return $data;
    }

    /**
     * Clean out sensitive data, such as credit-card numbers
     * @param array $data
     */
    private static function sanitize(array &$data)
    {
        $blackList = array_combine(self::config()->loggingBlacklist, self::config()->loggingBlacklist);
        array_walk_recursive($data, function (&$value, $key) use ($blackList) {
            if (isset($blackList[$key])) {
                $value = '(sanitized)';
            }
        });
    }
}
