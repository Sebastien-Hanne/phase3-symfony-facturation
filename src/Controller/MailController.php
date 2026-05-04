<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

final class MailController extends AbstractController
{
    #[Route('/mail', name: 'app_mail')]
    public function index(MailerInterface $mailer): Response
    {
        $email = new Email();
        $email->from("billy@gmail.com")
            ->to("mike@gmail.com")
            ->subject("On test le mailer")
            ->text("Bonjour je suis un petit test pour le mailer de symfony");

        $mailer->send($email);


        return $this->render('mail/index.html.twig', [
            'controller_name' => 'MailController',
        ]);
    }
}    
