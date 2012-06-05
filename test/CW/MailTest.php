<?php
/**
 * @author Oliver Tupman <oliver.tupman@centralway.com>
 * Date: 21/05/2012
 * Time: 09:23
 */


require_once('../bootstrap.php');
class CW_MailTest extends PHPUnit_Framework_TestCase
{
    public function setup() {
        $this->config = array();
        //TODO: these settings should come from the containing phpunit
        $this->config['mail'] = array(

        );
        CW_TestXBoilerplate::overrideStandardXBoilerplate();
        CW_TestXBoilerplate::$config = (object)$this->config;

    }

    /**
     * For testing purposes we create a specialisation of the standard mailer; it allows us to 'store' the mail that was
     * sent and access the various members, ensuring that the class functions as designed.
     *
     * Individual implementations, such as the standard mail() mailer, should be tested individually, however due to
     * their specialisation (i.e. calls to mail server, etc.) it is typically not possible.
     *
     * @return CW_MailTest_MockMailer
     */
    private function getTestMailer() {
        return new CW_MailTest_MockMailer(array()); // Never use new XXXMailer in real situations; use CW_Mail::getInstance() instead
    }

    /**
     * Tests that a simple mail will be sent with the correct subject, recipient, etc. as well as setting the default
     * mail headers.
     */
    public function testSimpleMailWithDefaultHeaders() {
        $mailer = $this->getTestMailer();

        $recipientAddress = 'test@example.com';
        $sender = 'sender@centralway.com';
        $subject = 'This is a simple mail';
        $content = 'Simple mail content';
        $mailer->to($recipientAddress)
            ->from($sender)
            ->subject($subject)
            ->content($content);

        $mailer->send();


        $this->assertEquals($recipientAddress, $mailer->getTo());
        $this->assertEquals($sender, $mailer->getFrom());
        $this->assertEquals($subject, $mailer->getSubject());
        $this->assertEquals($content, $mailer->getContent());


        $headers = $mailer->getHeaders();

        $this->assertArrayHasKey(CW_Mail::HEADER_FROM, $headers);
        $this->assertEquals($sender, $headers[CW_Mail::HEADER_FROM]);

        $this->assertArrayHasKey(CW_Mail::HEADER_CONTENT_TYPE, $headers);
        $this->assertEquals(CW_Mail::HEADERVAL_HTML, $headers[CW_Mail::HEADER_CONTENT_TYPE]);

        $this->assertArrayHasKey(CW_Mail::HEADER_MIME, $headers);
        $this->assertEquals(CW_Mail::HEADERVAL_MIME, $headers[CW_Mail::HEADER_MIME]);
    }

    public function testSend_withInternationalCharacters() {
        $mailer = $this->getTestMailer();
        $subject = 'ü ä ö > < ° £ ! ¨` ^ ¿ ≠ ± “ # Ç [ ] | { }';
        $content = strrev($subject);
        $mailer->to('test@example.com')
            ->from('sender@example.com')
            ->subject($subject)
            ->content($content);
        $mailer->send();

        $this->assertEquals($subject, $mailer->getSubject());
        $this->assertEquals($content, $mailer->getContent());
    }

    public function testContentType() {
        $mailer = $this->getTestMailer();

        $plainText = "Some plain text\ncontent.";
        $mailer->format(CW_Mail::FORMAT_TEXT)
            ->to('test@example.com')
            ->from('sender@example.com')
            ->subject('Test subject')
            ->content($plainText);

        $mailer->send();

        $headers = $mailer->getHeaders();
        $this->assertArrayHasKey(CW_Mail::HEADER_CONTENT_TYPE, $headers);

        $this->assertEquals(CW_Mail::HEADERVAL_TEXT, $headers[CW_MAIL::HEADER_CONTENT_TYPE]);

    }

    public function testHeaderOverriding() {
        $mailer = $this->getTestMailer();

        $mailer->to('test@example.com')
            ->from('sender@example.com')
            ->subject('Test subject')
            ->content('Some content');
        // Override content-type by name
        $contentType = 'application/json';
        $mailer->setHeader('Content-type', $contentType);

        $mailer->send();

        $headers = $mailer->getHeaders();
        $this->assertArrayHasKey(CW_Mail::HEADER_CONTENT_TYPE, $headers);
        $this->assertEquals($contentType, $headers[CW_MAIL::HEADER_CONTENT_TYPE]);
    }

    public function testPersonalisation() {
        $mailer = $this->getTestMailer();

        $mailer->to('test@example.com')
            ->from('sender@example.com')
            ->subject('Test subject')
            ->content('Some content');

        $mailer->personalisation('FIRSTNAME', 'Bob');
        $mailer->personalisation('LASTNAME', 'Pearson');
        $message = 'Dear ' . CW_Mail::SUBSTITUTION_PREFIX . 'FIRSTNAME' . CW_Mail::SUBSTITUTION_POSTFIX . ', <br/>Welcome!';
        $message.= 'Your surname, by the way, is '. CW_Mail::SUBSTITUTION_PREFIX . 'LASTNAME' . CW_Mail::SUBSTITUTION_POSTFIX;
        $mailer->content($message);

        $mailer->send();

        $this->assertTrue(strpos($mailer->getContent(), 'Bob') !== false, 'Firstname substitution failed.');
        $this->assertTrue(strpos($mailer->getContent(), 'Pearson') !== false, 'Lastname substitution failed.');
        $this->assertTrue(stripos($mailer->getContent(), '!*') === false, 'Found a substitution string still in the mail content!');
    }

    public function testSend_real() {
        $mailer = CW_Mail::create();

        $localAddress = 'vagrant@localhost';

        $senderAddress = 'example@example.com';
        $subject = 'Test subject';
        $content = 'Test content';
        $mailer->to($localAddress)
            ->from($senderAddress)
            ->subject($subject)
            ->content($content);

        $mailer->send();
        sleep(1);
        $mailerOutput = shell_exec("echo 1 | mail");
        echo 'output---' . "\n";
        $portions = preg_split('[\n]', $mailerOutput);
        $headerValues = array();
        array_walk(&$portions, function($item, $position) use(&$headerValues) {
            $validHeaders = array('From' => true, 'Subject' => true);
            $isPossibleHeader = stripos($item, ':');
            if($isPossibleHeader !== false) {
                $headerParts = preg_split('/:/', $item, 2);
                $header = $headerParts[0];
                if(array_key_exists($header, $validHeaders)) {
                    $headerValues[$header] = substr($headerParts[1], 1);
                }
            }
        });


        $this->assertArrayHasKey('From', $headerValues);
        $this->assertArrayHasKey('Subject', $headerValues);

        $this->assertEquals($senderAddress, $headerValues['From']);
        $this->assertEquals($subject, $headerValues['Subject']);
    }
}

class CW_MailTest_MockMailer extends CW_Mail {

    private $headers;
    private $content;

    public function getFrom() {
        return $this->_from;
    }

    public function getTo() {
        return $this->_to;
    }

    public function getHeaders() {
        return $this->headers;
    }

    public function getSubject() {
        return $this->_subject;
    }

    public function getContent() {
        return $this->content;
    }

    protected function doSend($content, array $headerValues) {
        $this->content = $content;
        $this->headers = $headerValues;
    }
}
