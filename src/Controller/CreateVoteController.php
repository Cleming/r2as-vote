<?php

namespace App\Controller;

use App\Entity\Events;
use App\Entity\Proposal;
use App\Entity\Users;
use App\Entity\ResponseType1;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Routing\Annotation\Route;
use League\Csv\Reader;
use League\Csv\Statement;
use Symfony\Component\Form\Extension\Core\Type\FileType;

class CreateVoteController extends AbstractController
{
    /**
     * @Route("/new-vote", name="create_vote")
     */
    public function index(Request $request, MailerInterface $mailer)
    {
        $uuid = uuid_create(UUID_TYPE_RANDOM);
        $event = new Events();
        $event->setUuid($uuid);
        $event->setState(true);
        $form = $this->createFormBuilder($event)
            ->add('name')
            ->add('description')
            ->add('mail', EmailType::class)
            ->add('save', SubmitType::class, ['label' => 'Valider'])
            ->getForm();
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $event = $form->getData();

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($event);
            $entityManager->flush();

            $mail = $form->get('mail')->getData();

            $email = (new TemplatedEmail())
                ->from('noemie.ployet@r2as.org')
                ->to($mail)
                ->subject('Admin : nouveau vote créé')
                ->htmlTemplate('emails/new_vote.html.twig')
                ->context([
                    'uuid' => $uuid,
                ]);

            $mailer->send($email);

            return $this->redirectToRoute('param_vote', ['uuid' => $uuid]);
        }


        return $this->render('create_vote/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/param-vote/{uuid}", name="param_vote")
     */
    public function paramvote(Request $request, $uuid)
    {
        $event = $this->getDoctrine()
            ->getRepository(Events::class)
            ->findOneBy(['uuid' => $uuid]);


        return $this->render('create_vote/param_vote.html.twig', [ 
            'uuid' => $uuid,
            'event' => $event,
        ]);
    }

    /**
     * @Route("/param-proposals/{uuid}", name="param_proposals")
     */
    public function paramproposals(Request $request, $uuid)
    {
        $event = $this->getDoctrine()
            ->getRepository(Events::class)
            ->findOneBy(['uuid' => $uuid]);
        $proposalss = $this->getDoctrine()
            ->getRepository(Proposal::class)
            ->findBy(['event_id' => $event]);
        $proposals = new Proposal();
        $proposals->setType("1");
        $proposals->setEventId($event);
        $form = $this->createFormBuilder($proposals)
            ->add('name')
            ->add('save', SubmitType::class, ['label' => 'Valider'])
            ->getForm();
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $proposals = $form->getData();

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($proposals);
            $entityManager->flush();

            return $this->redirectToRoute('param_vote', ['uuid' => $uuid]);
        }

        return $this->render('create_vote/param_proposals.html.twig', [ 
            'uuid' => $uuid,
            'event' => $event,
            'proposalss' => $proposalss,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/disable/{uuid}", name="disablevote")
     */
    public function disablevote(Request $request, $uuid)
    {
        $event = $this->getDoctrine()
            ->getRepository(Events::class)
            ->findOneBy(['uuid' => $uuid]);
        $event->setState(false);
        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($event);
        $entityManager->flush();

            return $this->redirectToRoute('param_vote', ['uuid' => $uuid]);
    }

    /**
     * @Route("/enable/{uuid}", name="enablevote")
     */
    public function enablevote(Request $request, $uuid)
    {
        $event = $this->getDoctrine()
            ->getRepository(Events::class)
            ->findOneBy(['uuid' => $uuid]);
        $event->setState(true);
        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($event);
        $entityManager->flush();

            return $this->redirectToRoute('param_vote', ['uuid' => $uuid]);
    }

    /**
     * @Route("/results/{uuid}", name="results")
     */
    public function results(Request $request, $uuid)
    {
        $event = $this->getDoctrine()
            ->getRepository(Events::class)
            ->findOneBy(['uuid' => $uuid]);
        $proposals = $this->getDoctrine()
            ->getRepository(Proposal::class)
            ->findBy(['event_id' => $event]);
        $responsesType1 = $this->getDoctrine()
            ->getRepository(ResponseType1::class)
            ->findBy(['event_id' => $event]);

        foreach ($responsesType1 as $r){
            $response[] = array(
                'proposal' => $r->getProposalId()->getId(),
                'positive' => $r->getpositive(),
                'negative' => $r->getnegative(),
                'abstention' => $r->getabstention(),
                'user' => $r->getUserId(),
            );
        }

        return $this->render('create_vote/results.html.twig', [
            'uuid' => $uuid,
            'event' => $event,
            'proposals' => $proposals,
            'responsesType1' => $response,
        ]);
    }

    /**
     * @Route("/delete-user/{uuid}/{userid}", name="deleteuser")
     */
    public function deleteuser(Request $request, $uuid, $userid)
    {
        $event = $this->getDoctrine()
            ->getRepository(Events::class)
            ->findOneBy(['uuid' => $uuid]);
        $user = $this->getDoctrine()
            ->getRepository(Users::class)
            ->findOneBy(['id' => $userid]);
        $userevent = $user->getEventId()->getId();
        $eventid = $event->getId();
        if ($userevent == $eventid){
            $this->getDoctrine()->getManager()->remove($user);
            $this->getDoctrine()->getManager()->flush();
        }

        return $this->redirectToRoute('param_users', ['uuid' => $uuid]);
    }

    /**
     * @Route("/delete-proposal/{uuid}/{proposalid}", name="deleteproposal")
     */
    public function deleteproposal(Request $request, $uuid, $proposalid)
    {
        $event = $this->getDoctrine()
            ->getRepository(Events::class)
            ->findOneBy(['uuid' => $uuid]);
        $proposal = $this->getDoctrine()
            ->getRepository(Proposal::class)
            ->findOneBy(['id' => $proposalid]);
        $proposalevent = $proposal->getEventId()->getId();
        $eventid = $event->getId();
        if ($proposalevent == $eventid){
            $this->getDoctrine()->getManager()->remove($proposal);
            $this->getDoctrine()->getManager()->flush();
        }

        return $this->redirectToRoute('param_proposals', ['uuid' => $uuid]);
    }

    /**
     * @Route("/list-users/{uuid}", name="list_users")
     */
    public function listusers(Request $request, $uuid)
    {
        $event = $this->getDoctrine()
            ->getRepository(Events::class)
            ->findOneBy(['uuid' => $uuid]);
        $users = $this->getDoctrine()
            ->getRepository(Users::class)
            ->findBy(['event_id' => $event]);

     return $this->render('create_vote/list_users.html.twig', [ 
            'uuid' => $uuid,
            'event' => $event,
            'users' => $users,
        ]);
    }

    /**
     * @Route("/param-users/{uuid}", name="param_users")
     */
    public function paramusers(Request $request, $uuid, MailerInterface $mailer)
    {
        $event = $this->getDoctrine()
            ->getRepository(Events::class)
            ->findOneBy(['uuid' => $uuid]);
        $users = new Users();
        $uuidd = uuid_create(UUID_TYPE_RANDOM);
        $users->setUuid($uuidd);
        $users->setEventId($event);
        $form = $this->createFormBuilder($users)
            ->add('mail', EmailType::class)
            ->add('name')
            ->add('factor', ChoiceType::class, [
                'choices' => [
                    '1' => '1',
                    '2' => '2',
                    '3' => '3',
                    '4' => '4',
                    '5' => '5',
                    '6' => '6',
                    '7' => '7',
                    '8' => '8',
                    '9' => '9',
                    '10' => '10',
                ],
            ])
            ->add('save', SubmitType::class, ['label' => 'Valider'])
            ->getForm();
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $users = $form->getData();

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($users);
            $entityManager->flush();

            $mail = $form->get('mail')->getData();

            $email = (new TemplatedEmail())
                ->from('noemie.ployet@r2as.org')
                ->to($mail)
                ->subject('Votre invitation au vote')
                ->htmlTemplate('emails/new_user.html.twig')
                ->context([
                    'uuid' => $uuidd,
                    'event' => $event,
                ]);

            $mailer->send($email);


            return $this->redirectToRoute('param_users', ['uuid' => $uuid]);
        }
     return $this->render('create_vote/param_users.html.twig', [ 
            'uuid' => $uuid,
            'event' => $event,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/param-users-batch/{uuid}", name="param_users_batch")
     */
    public function paramusersbatch(Request $request, $uuid, MailerInterface $mailer)
    {
        $event = $this->getDoctrine()
            ->getRepository(Events::class)
            ->findOneBy(['uuid' => $uuid]);
        $form = $this->createFormBuilder()
            ->add('csv', TextareaType::class)
            ->add('save', SubmitType::class, ['label' => 'Valider'])
            ->getForm();
        $entityManager = $this->getDoctrine()->getManager();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            
            $import = $form->get('csv')->getData();

            $reader = Reader::createFromString($import);
            $records = $reader->getRecords(['name', 'email', 'factor']);

            foreach ($records as $row => $record){
                $uuidd = uuid_create(UUID_TYPE_RANDOM);
                $users = new Users();
                $users
                    ->setUuid($uuidd)
                    ->setEventId($event)
                    ->setMail($record['email'])
                    ->setName($record['name'])
                    ->setFactor($record['factor'])
                ;
                $entityManager->persist($users);
                $entityManager->flush();

                $email = (new TemplatedEmail())
                    ->from('noemie.ployet@r2as.org')
                    ->to($record['email'])
                    ->subject('Votre invitation au vote')
                    ->htmlTemplate('emails/new_user.html.twig')
                    ->context([
                        'uuid' => $uuidd,
                        'event' => $event,
                    ]);

                $mailer->send($email);

            }
            return $this->redirectToRoute('param_users_batch', ['uuid' => $uuid]);
        }

     return $this->render('create_vote/param_users_batch.html.twig', [ 
            'uuid' => $uuid,
            'event' => $event,
            'form' => $form->createView(),
        ]);
    }
}

