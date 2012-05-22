<?php

/**
 * Simple class that enables one to send emails (with or without personalisation)
 *
 * This class will automatically set the following headers: MIME version, Content-type (based on whether it is
 * an HTML or plain-text email) and from. These can be overridden by calling setHeader().
 *
 */
abstract class CW_Mail {

    public static function createInstance($type, $config = array()) {
        return new $type($config);
    }

    public static function createInstanceFromConfig($config, $type = null) {

    }

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

    /**
     * Adds a name/value personalisation value to the mail being constructed.
     *
     * At the time of send, the personalisation values will be set and the message sent.
     * Repeated calls with the same personalisation name will overwrite the previous value.
     *
     * @param string $name the name of the personalisation string. No prefix/postfix are required
     * @param $value the value to set; must go nicely to a string
     * @return mixed the instance
     */
    public function personalisation($name, $value) {
        $this->_substitutions[$name] = $value;
        return $this;
    }

    /**
     * Batch-sets the personalisation using a name/value array
     *
     * @param $nameValueMap the name/value array (key: name, value: value) of personalisations to apply
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
     * @return mixed this mail instance
     */
    public function format($formatType) {
        $this->_formatType = $formatType;
        return $this;
    }

    /**
     * Sets a header by it's name with a particular value.
     *
     * @param string $name the name of the header - no trailing ':' required
     * @param $value the value to set for the header.
     */
    public function header($name, $value) {
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
            $personalisedMessage.= str_replace($substitutionName, $value, $personalisedMessage);
        }
        return $personalisedMessage;
    }


    protected function generateHeaders() {
        $headers = '';
        foreach($this->_headerValues as $name => $value) {
            $headers .= $name . ': ' . $value . "\r\n";
        }
        return $headers;
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
        $this->applyDefaultHeaders();
        $personalisedMessage = $this->applyPersonalisation($this->_message);
        $headers = $this->generateHeaders();
        return $this->doSend($personalisedMessage, $headers);
    }

    /**
     * Performs a 'test' send, one where no personlisation is added.
     *
     * @return mixed
     */
    public function sendTest() {
        $this->applyDefaultHeaders();
        $headers = $this->generateHeaders();
        return $this->doSend($this->_message, $headers);
    }


    abstract protected function doSend($content, $headers);

    /**
     * Applies a series of default headers to the email.
     */
    protected function applyDefaultHeaders()
    {
        if (!$this->hasHeader(CW_Mail::HEADER_CONTENT_TYPE)) {
            $this->setHeader(self::HEADER_CONTENT_TYPE, self::HEADERVAL_HTML);
        }
        if (!$this->hasHeader(self::HEADER_FROM)) {
            $this->setHeader(self::HEADER_FROM, $this->_from);
        }
        if (!$this->hasHeader(self::HEADER_MIME)) {
            $this->setHeader(self::HEADER_MIME, self::HEADERVAL_MIME);
        }
    }
    protected $config;
    public function __construct($config) {
        $this->config = $config;
    }
}

class CW_Sendmailer extends CW_Mail {
    private $_errstr;

    protected function doSend($content, $headers) {
        set_error_handler(array($this, '_handleMailErrors'));
        $wasSuccess = mail($this->_to, $this->_subject, $content, $headers);
        restore_error_handler();
        return $wasSuccess;
    }

    public function _handleMailErrors($errno, $errstr, $errfile = null, $errline = null, array $errcontext = null)
    {
        $this->_errstr = $errstr;
        return true;
    }
}

class CW_QueuingMailer extends CW_Mail {
    private $_errstr;

    protected function doSend($content, $headers) {
        $this->writeEmailRow(
            $this->config['dbhost'],
            $this->config['username'], $this->config['password'],
            $this->config['dbschema'], $this->config['mailtable'],
            $content, $headers
        );
        return true;
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

class CW_SmtpMailer extends CW_Mail {
    protected function doSend($content, $headers) {

    }
}