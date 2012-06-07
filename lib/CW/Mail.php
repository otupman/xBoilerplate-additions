<?php

/**
 * Simple class that enables one to send emails (with or without personalisation)
 *
 * This class will automatically set the following headers: MIME version, Content-type (based on whether it is
 * an HTML or plain-text email) and from. These can be overridden by calling setHeader().
 *
 * Configuration:
 * 'mail':
 *  'headers': associative array of additional headers, name/value
 */
abstract class CW_Mail {

    /**
     * @static
     * @return CW_Mail
     * @throws Exception
     */
    public static function create() {
        $xConfig = (array)xBoilerplate::getInstance()->getConfig();
        $mailConfig = array();
        if(array_key_exists(self::CONFIG_MAIL, $xConfig)) {
            $mailConfig = $xConfig[self::CONFIG_MAIL];
        }
        $mailerClass = array_key_exists(self::CONFIG_MAILERCLASS, $mailConfig)
            ? $mailConfig[self::CONFIG_MAILERCLASS]
            : self::MAILER_SENDMAIL;

        return new $mailerClass($mailConfig);
    }

    const CONFIG_MAIL = 'mail';
    const CONFIG_MAILERCLASS = 'mailer';
    const CONFIG_HEADERS = 'headers';

    const MAILER_SENDMAIL = 'CW_Sendmailer';

    const SUBSTITUTION_PREFIX = '!*';
    const SUBSTITUTION_POSTFIX = '*!';

    /** Email is in HTML format (default) */
    const FORMAT_HTML = true;
    /** Email is in plain text format */
    const FORMAT_TEXT = false;

    /**
     * @var string
     */
    protected $_from;

    /**
     * @var string
     */
    protected $_to;

    /**
     * @var string
     */
    protected $_subject;

    /**
     * @var string
     */
    protected $_message;

    protected $_formatType = self::FORMAT_HTML;

    private $_substitutions = array();
    private $_headerValues = array();

    protected $config;

    public function __construct($config) {
        $this->loadConfig($config);
    }

    private function loadConfig($config) {
        $this->config = $config;
        if(array_key_exists(self::CONFIG_HEADERS, $this->config)) {
            $this->_headerValues = $this->config[self::CONFIG_HEADERS];
        }
    }

    public function to($recipientAddress) {
        $this->_to = $recipientAddress;
        return $this;
    }

    public function from($senderAddress) {
        $this->_from = $senderAddress;
        return $this;
    }

    public function content($emailContent) {
        $this->_message = $emailContent;
        return $this;
    }

    public function subject($subject) {
        $this->_subject = $subject;
        return $this;
    }

    public function getRecipient() {
        return $this->_to;
    }

    public function getSender() {
        return $this->_from;
    }

    public function getSubject() {
        return $this->_subject;
    }

    /**
     * Adds a name/value personalisation value to the mail being constructed.
     *
     * At the time of send, the personalisation values will be set and the message sent.
     * Repeated calls with the same personalisation name will overwrite the previous value.
     *
     * @param string $name the name of the personalisation string. No prefix/postfix are required
     * @param string $value the value to set; must go nicely to a string
     * @return CW_Mail the instance
     */
    public function personalisation($name, $value) {
        $this->_substitutions[$name] = $value;
        return $this;
    }

    /**
     * Batch-sets the personalisation using a name/value array
     *
     * @param array|associative $nameValueMap the name/value array (key: name, value: value) of personalisations to apply
     * @return CW_Mail the instance
     */
    public function personalisations($nameValueMap) {
        foreach($nameValueMap AS $name => $value) {
            $this->personalisation($name, $value);
        }
        return $this;
    }

    /**
     * Sets the format type of the email: text or HTML.
     *
     * @param bool $formatType true - HTML, false - text try to use CW_Mail::HTML and CW_TEXT
     * @return CW_Mail this mail instance
     */
    public function format($formatType) {
        $this->_formatType = $formatType;
        return $this;
    }

    /**
     * Sets a header by it's name with a particular value.
     *
     * @param string $name the name of the header - no trailing ':' required
     * @param string $value the value to set for the header.
     * @return CW_Mail this mail instance
     */
    public function setHeader($name, $value) {
        $this->_headerValues[$name] = $value;
        return $this;
    }

    private function hasHeader($name) {
        return array_key_exists($name, $this->_headerValues);
    }

    protected function applyPersonalisation($message) {
        $personalisedMessage = $message;
        foreach($this->_substitutions as $name => $value) {
            $substitutionName = self::SUBSTITUTION_PREFIX . $name . self::SUBSTITUTION_POSTFIX;
            $personalisedMessage = str_replace($substitutionName, $value, $personalisedMessage);
        }
        return $personalisedMessage;
    }

    const HEADER_CONTENT_TYPE = 'Content-type';
    const HEADER_FROM = 'From';
    const HEADER_MIME = 'MIME-Version';

    const HEADERVAL_HTML = 'text/html; charset=iso-utf8';
    const HEADERVAL_TEXT = 'text/plain; charset=iso-utf8';
    const HEADERVAL_MIME = '1.0';

    /**
     * Sends the mail to the recipient, applying any personalisation required.
     *
     */
    public function send() {
        $headers = $this->applyDefaultHeaders($this->_headerValues);
        $headers = $this->applyConditionalHeaders($headers);
        $personalisedMessage = $this->applyPersonalisation($this->_message);
        $this->doSend($personalisedMessage, $headers);
    }

    /**
     * @abstract
     * @param $content
     * @param array $headers
     * @return mixed
     */
    abstract protected function doSend($content, array $headers);

    /**
     * Applies a series of default headers to the email.
     */
    protected function applyDefaultHeaders($headerValues)
    {
        if (!array_key_exists(self::HEADER_CONTENT_TYPE, $headerValues)) {
            $headerValues[self::HEADER_CONTENT_TYPE] = self::HEADERVAL_HTML;
        }
        if (!array_key_exists(self::HEADER_FROM, $headerValues)) {
            $headerValues[self::HEADER_FROM] = $this->_from;
        }
        if (!array_key_exists(self::HEADER_MIME, $headerValues)) {
            $headerValues[self::HEADER_MIME] = self::HEADERVAL_MIME;
        }
        if (!array_key_exists(self::HEADER_FROM, $headerValues)) {
            $headerValues[self::HEADER_FROM] = $this->_from;
        }
        return $headerValues;
    }

    protected function applyConditionalHeaders(array $headerValues) {
        if($this->_formatType == self::FORMAT_TEXT) {
            $headerValues[self::HEADER_CONTENT_TYPE] = self::HEADERVAL_TEXT;
        }
        return $headerValues;
    }

}

class CW_Sendmailer extends CW_Mail {
    private $_errstr;


    protected function generateHeaders($headerValues) {
        $headers = '';
        foreach($headerValues as $name => $value) {
            $headers .= $name . ': ' . $value . "\r\n";
        }
        return $headers;
    }

    protected function doSend($content, array $headerValues) {
        set_error_handler(array($this, '_handleMailErrors'));
        $headers = $this->generateHeaders($headerValues);
        $wasSuccess = mail($this->_to, $this->_subject, $content, $headers);
        restore_error_handler();
        if(!$wasSuccess) {
            throw new CW_MailException($this, "Error sending mail: " . $this->_errstr);
        }
    }

    public function _handleMailErrors($errno, $errstr, $errfile = null, $errline = null, array $errcontext = null)
    {
        $this->_errstr = $errstr . '(errno: ' . $errno . ')';
        return true;
    }
}

class CW_QueuingMailer extends CW_Mail {
    private $_errstr;

    protected function doSend($content, array $headerValues) {
        $this->writeEmailRow(
            $this->config['dbhost'],
            $this->config['username'], $this->config['password'],
            $this->config['dbschema'], $this->config['mailtable'],
            $content, serialize($headerValues)
        );
    }

    private function writeEmailRow($dbHost, $dbUser, $dbPass, $db, $tableName, $content, $headers) {
        $connection = new mysqli($dbHost, $dbUser, $dbPass, $db);
        $query = 'INSERT INTO ' . $tableName . ' (recipient, sender, createTime, content, headers)';
        $query.= ' VALUES ("' . $this->_to . '", "' . $this->_from . '", NOW(), "' . $content . '", "' . $headers . '")';
        if(!$connection->query($query)) {
            $this->_errstr = 'Mail Send Failed: ' . $connection->error;
            return false;
        }
        else {
            $this->_errstr = '';
            return true;
        }
    }
}
