<?php

namespace Ambta\DoctrineEncryptBundle\Tests\Encryptors;

use Ambta\DoctrineEncryptBundle\Encryptors\Rijndael128Encryptor;

class Rijndael128EncryptorTest extends \PHPUnit_Framework_TestCase 
{

    
    protected $encryptor = null;

    public function setUp() 
    {
        $this->encryptor = new Rijndael128Encryptor('thisistheawesomepass');
    }
    
    public function testEncrypt() 
    {
        $this->assertEquals('Pv6l25nCgtyeFvHScrX3kw==<ENC>', $this->encryptor->encrypt('partylikeits1999'));
    }

    public function testDecrypt()
    {
        $this->assertEquals('mychinnychinchin', $this->encryptor->decrypt('xD1hxsssgpHwoeo7rbGxSg==<ENC>'));
    }
}
