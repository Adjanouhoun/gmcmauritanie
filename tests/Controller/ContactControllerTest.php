<?php
namespace App\Tests\Controller;

use App\Controller\ContactController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Mime\Email;

final class ContactControllerTest extends TestCase
{
    private function data(): array
    {
        return ['_token'=>'token', 'name'=>'Visiteur', 'email'=>'visitor@example.org', 'subject'=>'Devis', 'message'=>'Bonjour'];
    }
    private function controller(bool $validToken, bool $send = false, bool $fail = false, string $dsn = 'smtp://example.invalid', string $from = 'contact@gmcmauritanie.com', string $to = 'contact@gmcmauritanie.com'): ContactController
    {
        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturn($validToken);
        $transport = $this->createMock(TransportInterface::class);
        if ($send) {
            $expectation=$transport->expects(self::once())->method('send')->with(self::callback(function ($mail) use ($from,$to) {
                return $mail instanceof Email && $mail->getTo()[0]->getAddress()===$to && $mail->getFrom()[0]->getAddress()===$from && $mail->getReplyTo()[0]->getAddress()==='visitor@example.org';
            }));
            if ($fail) $expectation->willThrowException(new TransportException('Private SMTP detail'));
        } else $transport->expects(self::never())->method('send');
        return new ContactController($transport, $csrf, $dsn, $from, $to);
    }
    public function testInvalidCsrfDoesNotSend(): void
    {
        self::assertSame(403,$this->controller(false)->send(Request::create('/', 'POST', $this->data()))->getStatusCode());
    }
    public function testInvalidFieldsDoNotSend(): void
    {
        foreach ([['email'=>'invalid'], ['name'=>['array']], ['subject'=>"Injected\r\nHeader"], ['message'=>''], ['message'=>str_repeat('a',10001)]] as $invalid) {
            self::assertSame(422,$this->controller(true)->send(Request::create('/', 'POST', array_replace($this->data(),$invalid)))->getStatusCode());
        }
    }
    public function testDisabledTransportDoesNotClaimSuccess(): void
    {
        self::assertSame(503,$this->controller(true,dsn:'null://null')->send(Request::create('/', 'POST', $this->data()))->getStatusCode());
    }
    public function testSuccessSendsOneMessage(): void
    {
        self::assertSame(200,$this->controller(true,true)->send(Request::create('/', 'POST', $this->data()))->getStatusCode());
    }
    public function testConfiguredSenderAndRecipientAreUsed(): void
    {
        self::assertSame(200,$this->controller(true,true,from:'sender@example.org',to:'reception@example.org')->send(Request::create('/', 'POST', $this->data()))->getStatusCode());
    }
    public function testInvalidMailConfigurationDoesNotSend(): void
    {
        foreach (['invalid', "mail@example.org\r\nBcc: attacker@example.org"] as $invalid) {
            self::assertSame(503,$this->controller(true,from:$invalid)->send(Request::create('/', 'POST', $this->data()))->getStatusCode());
            self::assertSame(503,$this->controller(true,to:$invalid)->send(Request::create('/', 'POST', $this->data()))->getStatusCode());
        }
    }
    public function testSmtpFailureDoesNotExposeDetails(): void
    {
        $response=$this->controller(true,true,true)->send(Request::create('/', 'POST', $this->data()));
        self::assertSame(503,$response->getStatusCode());
        self::assertStringNotContainsString('Private SMTP detail',$response->getContent());
    }
}
