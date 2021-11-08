<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class DbTest extends KernelTestCase
{

    // Initialisation de la classe (nécessaire pour utiliser directement entity manager)
    protected function setUp()
    {
        static::bootKernel();
    }

    // Teste si la connexion à la base de données est bien établie
    public function testDbConnection()
    {
        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $conn = $em->getConnection();
        $sql = "SELECT table_name
            FROM information_schema.tables
            WHERE table_schema = 'public'
            ORDER BY table_name;";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $this->assertTrue(count($stmt->fetchAll()) >= 0);
    }
}
