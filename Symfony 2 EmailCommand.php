<?php
namespace OS\CoreBundle\Command;

use OS\CoreBundle\Entity\EmailLog;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class EmailCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName('email:retrieve')->setDescription('task will retrieve emails from catchall server,parse thread_id,user_id and saves to thread(email_log) in DB ');

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $container = $this->getContainer();
        $imapParams = $container->getParameter('imap_mail_checker');


        $em = $container->get('doctrine')->getEntityManager('default');
        $options = new \ezcMailImapTransportOptions();
        $options->ssl = true;
        $imapClient = new \ezcMailImapTransport($imapParams['host'], $imapParams['port'], $options);
        $imapClient->authenticate($imapParams['login'], $imapParams['password']);
        $imapClient->selectMailbox($imapParams['folder']);

        $parser = new \ezcMailParser();
        $mailArray = $parser->parseMail($imapClient->fetchAll(true));
        /* @var $mail \ezcMail */
        $savedCounter=0;
        foreach ($mailArray as $key=>$mail) {

            $message = $mail->to[0];
            $body = null;
            $matches = [];
            preg_match("`^thread-([a-f0-9]{32})-([a-f0-9]{32})@notifications\.blablabla\.com$`", $message->email, $matches);
            if ($matches) {
                $emailFrom = $mail->from->email;
                $userFrom = $em->getRepository('OSCoreBundle:User')->findOneBy(array('emailCanonical' => $emailFrom));
                if ($userFrom) {
                    foreach ($mail->fetchParts() as $part) {
                        /* @var $part \ezcMailText */
                        if ($part instanceof \ezcMailText) {
                            $body = ($part->generateBody());
                            break;
                        }
                    }
                    if ($body) {
                        $threadHash = $matches[1];
                        $userHash = $matches[2];
                        $thread = $em->getRepository('OSCoreBundle:Thread')->findOneBy(array('hash' => $threadHash));
                        $userTo = $em->getRepository('OSCoreBundle:User')->findOneBy(array('hash' => $userHash));

                        $log = new EmailLog();
                        $log->setUserFrom($userFrom);
                        $log->setUserTo($userTo);
                        $log->setSubject($thread->getSubject());
                        $bodyarr = explode("\n",$body);
                        $above_key_arr = array_keys(preg_grep("/-- reply ABOVE THIS LINE to add a comment to this message --/", $bodyarr));
                        $above_line = array_pop($above_key_arr);
                        array_splice($bodyarr, $above_line);
                        $res = array_filter($bodyarr,function($val){
                            preg_match("/^>\s$|^\s$|^>$/",$val,$str_matches);
                            if($str_matches){
                                return false;
                            }else{
                                return true;
                            }
                        });
                        $lastel = array_pop($res);
                        if(preg_match_all( "/[0-9]/", $lastel )<6 && preg_match_all( "/@/", $lastel )<1)
                        {
                            $res[] = $lastel;
                        }
                        $body = implode("\n",$res);
                        $log->setBody(nl2br(trim($body)));
                        $log->setThread($thread);
                        $log->setCreatedAt(new \DateTime(date('Y-m-d H:i:s', $mail->timestamp)));
                        $em->persist($log);
                        $em->flush();
                        $savedCounter++;
                        $output->writeln('[NEW] message from '.$emailFrom. ' added!');


                    }
                    $imapClient->delete( $key+1);//message number
                    $imapClient->expunge();
                }else{
                    $output->writeln('[SKIP] User not found with email:'.$emailFrom);
                }

            }
        }
        $output->writeln('total messages recieved :'.count($mailArray));
        $output->writeln('total messages saved :'.$savedCounter);

    }
}