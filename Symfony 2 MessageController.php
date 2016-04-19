<?php

namespace OS\CoreBundle\Controller;


use OS\CoreBundle\Entity\EmailLog;
use OS\CoreBundle\Entity\Thread;
use OS\CoreBundle\Form\UserMessageType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class MessageController extends Controller
{
    public function sendMessageAction(Request $request, $userId)
    {
        $em = $this->getDoctrine()->getManager();
        $user = $this->get('security.context')->getToken()->getUser();
        if (!$user->getHash()) {
            $user->setHash(md5($user->getId() . 'blalbalbasalt'));
            $em->persist($user);
            $em->flush();
        }
        $customer = $em->getRepository('OSCoreBundle:User')->find($userId);
        $form = $this->createForm(new UserMessageType($em));
        if (!$customer) {
            throw $this->createNotFoundException('Unable to find User entity.');
        }

        $form->handleRequest($request);
        $jsondata = $em->getRepository('OSCoreBundle:SystemEmail')->getJsonData();

        if ($form->isValid()) {
            if (!$customer->getHash()) {
                $customer->setHash(md5($userId . 'blalbalbasalt'));
                $em->persist($customer);
                $em->flush();
            }

            $thread = new Thread();
            $em->persist($thread);
            $em->flush();
            $thread->setHash(md5($thread->getId() . 'blalbalbasalt'));
            $data = $form->getData(); //form
            $thread->setSubject($data['subject']);
            $em->persist($thread);
            $log = new EmailLog();
            $log->setUserFrom($user);
            $log->setUserTo($customer);
            $log->setSubject($data['subject']);
            $log->setBody($data['body']);
            $log->setSystemEmail($data['system_email']);
            $log->setThread($thread);
            $em->persist($log);
            $em->flush();
            $mailer = $this->get('mailer');
            $fromHashedAddr = 'thread-' . $thread->getHash() . '-' . $customer->getHash() . '@notifications.blalbalba.com';
            $message = $mailer->createMessage()
                ->setSubject('RE:[' . $thread->getSubject() . ']')
                ->setFrom('notifications@notifications.blalbalba.com')
                ->setReplyTo($fromHashedAddr)
                ->setTo($customer->getEmailCanonical())
                ->setBody($this->renderView(
                    'OSCoreBundle:Message:email_template.html.twig',
                    array('user' => $customer, 'subject' => $thread->getSubject(), 'body' => $data['body'])), 'text/html');
            $mailer->send($message);
            $this->get('session')->getFlashBag()->add('notice', 'Your message has been sent!');

            return $this->redirect($this->generateUrl('thread', array('id' => $thread->getId())));
        }
        return $this->render('OSCoreBundle:Message:sendMessage.html.twig', array(
            'user' => $customer,
            'admin' => $user,
            'form' => $form->createView(),
            'jsondata' => $jsondata
        ));
    }

    public function threadAction(Request $request, $id)
    {
        $em = $this->getDoctrine()->getManager();


        $user = $this->get('security.context')->getToken()->getUser();
        if (!$user->getHash()) {
            $user->setHash(md5($user->getId() . 'blalbalbasalt'));
            $em->persist($user);
            $em->flush();
        }
        $thread = $em->getRepository('OSCoreBundle:Thread')->getWithMessages($id);

        if (!$user) {
            throw $this->createNotFoundException('Unable to find User entity.');
        }

        $form = $this->createFormBuilder()
            ->add('body', 'textarea')
            ->add('system_email', 'entity', array(
                'class' => 'OSCoreBundle:SystemEmail',
                'required' => false,
                'multiple' => false,
                'expanded' => false,
                'empty_data' => null,
                'empty_value' => '-- Select system template --',

            ))
            ->getForm();


        $form->handleRequest($request);
        $data = $form->getData(); //form
        $jsondata = $em->getRepository('OSCoreBundle:SystemEmail')->getJsonData();
        $customer = $thread->getMessages()[0]->getUserTo();

        if ($form->isValid()) {
            if (!$customer->getHash()) {
                $customer->setHash(md5($customer->getId() . 'blalbalbasalt'));
                $em->persist($customer);
                $em->flush();
            }
            $log = new EmailLog();
            $log->setUserFrom($user);
            $log->setUserTo($customer);
            $log->setSubject($thread->getSubject());
            $log->setBody($data['body']);
            $log->setSystemEmail($data['system_email']);
            $log->setThread($thread);
            $em->persist($log);
            $em->flush();
            $mailer = $this->get('mailer');
            $fromHashedAddr = 'thread-' . $thread->getHash() . '-' . $customer->getHash() . '@notifications.blalbalba.com';
            $message = $mailer->createMessage()
                ->setSubject('RE:[' . $thread->getSubject() . ']')
                ->setFrom('notifications@notifications.blalbalba.com')
                ->setReplyTo($fromHashedAddr)
                ->setTo($customer->getEmailCanonical())
                ->setBody($this->renderView(
                    'OSCoreBundle:Message:email_template.html.twig',
                    array('user' => $customer, 'subject' => $thread->getSubject(), 'body' => $data['body'])), 'text/html');
            $mailer->send($message);
            $this->get('session')->getFlashBag()->add('notice', 'Your message has been sent!');

            return $this->redirect($this->generateUrl('thread', array('id' => $thread->getId())));
        }

        return $this->render('OSCoreBundle:Message:thread.html.twig', array(
                'thread' => $thread,
                'form' => $form->createView(),
                'user' => $customer,
                'admin' => $user,
                'jsondata' => $jsondata
            )
        );
    }


}
