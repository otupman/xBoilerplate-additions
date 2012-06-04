<?php
/**
 * Mail Exception for any errors occurred while processing & sending emails.
 *
 * The exception will contain a few emails that are hopefully useful:
 *  - the error message supplied by the caller
 *  - the recipient
 *  - the sender
 *  - the subject
 *
 * @author Oliver Tupman <oliver.tupman@centralway.com>
 * Date: 04/06/2012
 * Time: 11:14
 */
class CW_MailException extends RuntimeException {
    private $_recipient;
    private $_sender;
    private $_subject;

    /**
     * Creates a new instance of the exception
     *
     * @param CW_Mail $mailer the mailer that is raising the exception
     * @param string $message the message for the exception
     * @param Exception $exception optional; any exception that may have caused this one
     */
    public function __construct(CW_Mail $mailer, $message, $exception = null) {
        parent::__construct($message, 0, $exception);
        $this->_recipient = $mailer->getRecipient();
        $this->_subject = $mailer->getSubject();
        $this->_sender = $mailer->getSender();
    }

    /**
     * @return string the recipient of the email
     */
    public function getRecipient()
    {
        return $this->_recipient;
    }

    /**
     * @return string the sender of the email
     */
    public function getSender()
    {
        return $this->_sender;
    }

    /**
     * @return string the subject (line) of the email
     */
    public function getSubject()
    {
        return $this->_subject;
    }


}