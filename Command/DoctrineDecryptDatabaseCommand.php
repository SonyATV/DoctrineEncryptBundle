<?php

namespace Ambta\DoctrineEncryptBundle\Command;

use Ambta\DoctrineEncryptBundle\DependencyInjection\DoctrineEncryptExtension;
use Doctrine\Common\Annotations\AnnotationReader;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * Hello World command for demo purposes.
 *
 *
 * @author Marcel van Nuil <marcel@ambta.com>
 */
class DoctrineDecryptDatabaseCommand extends ContainerAwareCommand
{
    protected $entityList = array();
    protected $propertyList = array();

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('doctrine:decrypt:database')
            ->setDescription('Decrypt whole database on tables which are encrypted')
            ->addArgument("encryptor", InputArgument::OPTIONAL, "The encryptor u want to decrypt the database with");
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        //Get entity manager, question helper, subscriber service and annotation reader
        $entityManager = $this->getContainer()->get('doctrine.orm.entity_manager');
        $question = $this->getHelper('question');
        $subscriber = $this->getContainer()->get('ambta_doctrine_encrypt.subscriber');
        $annotationReader = new AnnotationReader();

        //Get list of supported encryptors
        $supportedExtensions = DoctrineEncryptExtension::$supportedEncryptorClasses;
        
        $entityManager->getEventManager()->removeEventSubscriber($this->getContainer()->get('ambta_doctrine_encrypt.subscriber'));

        //If encryptor has been set use that encryptor else use default
        if($input->getArgument('encryptor')) {
            if(isset($supportedExtensions[$input->getArgument('encryptor')])) {
                $subscriber->setEncryptor($supportedExtensions[$input->getArgument('encryptor')]);
            } else {
                if(class_exists($input->getArgument('encryptor')))
                {
                    $subscriber->setEncryptor($input->getArgument('encryptor'));
                } else {
                    $output->writeln("\nGiven encryptor does not exists");
                    $output->writeln("Supported encryptors: " . implode(", ", array_keys($supportedExtensions)));
                    $output->writeln("You can also define your own class. (example: Ambta\DoctrineEncryptBundle\Encryptors\Rijndael128Encryptor)");
                    return;
                }
            }
        }

        //Get entity manager metadata
        $metaDataArray = $entityManager->getMetadataFactory()->getAllMetadata();

        //Set counter and loop through entity manager meta data
        $propertyCount = 0;
        foreach($metaDataArray as $metaData) {
            if ($metaData->isMappedSuperclass) {
                continue;
            }
            
            $this->propertyList[$metaData->name] = array();
            
            //Create reflectionClass for each entity
            $reflectionClass = New \ReflectionClass($metaData->name);
            $propertyArray = $reflectionClass->getProperties();

            //Count propperties in metadata
            foreach($propertyArray as $property) {
                if($annotationReader->getPropertyAnnotation($property, "Ambta\DoctrineEncryptBundle\Configuration\Encrypted")) {
                    $propertyCount++;
                    array_push($this->propertyList[$metaData->name], $property);
                    if(!array_key_exists($metaData->name, $this->entityList)) {
                        $this->entityList[$metaData->name] = $metaData;
                    }
                }
            }
        }

        $confirmationQuestion = new ConfirmationQuestion("<question>\n" . count($metaDataArray) . " entitys found which are containing " . $propertyCount . " properties with the encryption tag. \n\nWhich are going to be decrypted with [" . $subscriber->getEncryptor() . "]. \n\nWrong settings can mess up your data and it will be unrecoverable. \nI advise you to make <bg=yellow;options=bold>a backup</bg=yellow;options=bold>. \n\nContinu with this action? (y/yes)</question>", false);

        if (!$question->ask($input, $output, $confirmationQuestion)) {
            return;
        }

        //Start decrypting database
        $output->writeln("\nDecrypting all fields this can take up to several minutes depending on the database size.");

        $valueCounter = 0;

        //Loop through entity manager meta data
        foreach($this->entityList as $metaData) {
            if(count($this->propertyList[$metaData->name]) == 0) {
                continue;
            }

            //Create reflectionClass for each meta data object
            $reflectionClass = New \ReflectionClass($metaData->name);
            
            //If class is not an superclass
            if (!$annotationReader->getClassAnnotation($reflectionClass, "Doctrine\ORM\Mapping\MappedSuperclass")) {

                /**
                 * Get repository and entity Array
                 * @var \Doctrine\ORM\EntityRepository $repository
                 */
                $repository = $entityManager->getRepository($metaData->name);
                $entityArray = $repository->findAll();
                
                $progress = new ProgressBar($output, count($entityArray));
                $progress->start();

                $progress = new ProgressBar($output, count($entityArray));
                $progress->start();

                foreach($entityArray as $entity) {
                    $entity = $subscriber->processFields($entity, false);
                    
                    //Persist and flush entity
                    $entityManager->persist($entity);
                    $entityManager->flush($entity);
                    $progress->advance();
                }
                
                $progress->finish();
            }
        }
        
        $entityManager->getEventManager()->addEventSubscriber($this->getContainer()->get('ambta_doctrine_encrypt.subscriber'));

        //Say it is finished
        $output->writeln("\nDecryption finished values found: " . $valueCounter . ", decrypted: " . $subscriber->decryptCounter . ".\nAll values are now decrypted.");
    }
}
