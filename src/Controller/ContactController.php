<?php

namespace App\Controller;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class ContactController
{
    public function __construct(
        private readonly TransportInterface $transport,
        private readonly CsrfTokenManagerInterface $csrf,
        #[Autowire('%env(MAILER_DSN)%')] private readonly string $mailerDsn,
        #[Autowire('%env(MAILER_FROM)%')] private readonly string $mailerFrom,
        #[Autowire('%env(MAILER_TO)%')] private readonly string $mailerTo,
    ) {}

    #[Route('/contact/envoyer', name: 'app_contact_send', methods: ['POST'])]
    public function send(Request $request): JsonResponse
    {
        $data = $request->request->all();
        $token = $data['_token'] ?? '';
        if (!is_string($token) || !$this->csrf->isTokenValid(new CsrfToken('contact', $token))) {
            return new JsonResponse(['status' => 'error', 'message' => 'La session a expiré. Rechargez la page avant de réessayer.'], 403);
        }
        $fields = [];
        foreach (['name' => 120, 'email' => 254, 'subject' => 200, 'message' => 10000] as $key => $max) {
            $value = $data[$key] ?? null;
            if (!is_string($value) || trim($value) === '' || mb_strlen($value) > $max) {
                return new JsonResponse(['status' => 'error', 'message' => 'Veuillez remplir tous les champs et respecter leur longueur maximale.'], 422);
            }
            $fields[$key] = trim($value);
        }
        if (!filter_var($fields['email'], FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $fields['email'].$fields['name'].$fields['subject'])) {
            return new JsonResponse(['status' => 'error', 'message' => 'Veuillez vérifier votre nom, votre adresse e-mail et le sujet.'], 422);
        }
        if (str_starts_with($this->mailerDsn, 'null://') || !filter_var($this->mailerFrom, FILTER_VALIDATE_EMAIL) || !filter_var($this->mailerTo, FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(['status' => 'error', 'message' => 'L’envoi est momentanément indisponible. Utilisez les coordonnées affichées sur cette page.'], 503);
        }
        $email = (new Email())
            ->from($this->mailerFrom)
            ->to($this->mailerTo)
            ->replyTo($fields['email'])
            ->subject('Contact Site Web : '.$fields['subject'])
            ->text("Nom : {$fields['name']}\nE-mail : {$fields['email']}\n\n{$fields['message']}");
        try {
            // Send synchronously so success means the transport accepted the message.
            $this->transport->send($email);
        } catch (TransportExceptionInterface) {
            return new JsonResponse(['status' => 'error', 'message' => 'Le message n’a pas pu être envoyé. Veuillez réessayer plus tard.'], 503);
        }
        return new JsonResponse(['status' => 'success', 'message' => 'Votre message a été envoyé.']);
    }
}
