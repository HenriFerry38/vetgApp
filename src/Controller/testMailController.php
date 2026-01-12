<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class testMailController extends AbstractController
{
    #[Route('/api/mail/test', methods: ['GET'])]
    public function testMail(MailerInterface $mailer): JsonResponse
    {
        $email = (new Email())
            ->from('no-reply@viteetgourmand.fr')
            ->to('test@exemple.fr')
            ->subject('Test Mailpit')
            ->text('Si tu vois ce mail dans Mailpit, c’est gagné ✅');

        $mailer->send($email);

        return new JsonResponse(['message' => 'Email envoyé ✅']);
    }
}