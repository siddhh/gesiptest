<?php

namespace App\Tests;

use PHPUnit\Runner\BeforeFirstTestHook;

class DatabaseMigrationExtension implements BeforeFirstTestHook
{

    /**
     * Cette méthode sera executée avant d'effectuer le premier test.
     */
    public function executeBeforeFirstTest(): void
    {
        // Initialisation
        $avecInitialisation = true;
        $output = null;
        $retVal = null;

        // Si nous souhaitons l'initialisation de la base pour faire passer les tests (vrai par défaut !)
        if ($avecInitialisation) {
            // Suppression base de données de test
            echo "\033[0;32m-> Suppression de la base de données de test.\033[0m" . PHP_EOL;
            exec('php ./bin/console doctrine:database:drop --env test --force', $output, $retVal);
            $this->checkExecution($retVal, $output);

            // Création base de données de test
            echo "\033[0;32m-> Création de la base de données de test.\033[0m" . PHP_EOL;
            exec('php ./bin/console doctrine:database:create --env test', $output, $retVal);
            $this->checkExecution($retVal, $output);

            // Migration base de données de test
            echo "\033[0;32m-> Lancement des migrations de la base de données de test.\033[0m" . PHP_EOL;
            exec('php ./bin/console doctrine:migrations:migrate -n --env test', $output, $retVal);
            $this->checkExecution($retVal, $output);

            // Rollback des migrations base de données de test
            echo "\033[0;32m-> Rollback complet des migrations de la base de données de test.\033[0m" . PHP_EOL;
            exec('php ./bin/console doctrine:migrations:migrate first -n --env test', $output, $retVal);
            $this->checkExecution($retVal, $output);

            // Remigration base de données de test
            echo "\033[0;32m-> Relancement des migrations de la base de données de test.\033[0m" . PHP_EOL;
            exec('php ./bin/console doctrine:migrations:migrate -n --env test', $output, $retVal);
            $this->checkExecution($retVal, $output);

            // Ajout fixtures base de données de test
            echo "\033[0;32m-> Ajout des fixtures dans la base de données de test.\033[0m" . PHP_EOL;
            exec('php ./bin/console doctrine:fixtures:load -n --env test', $output, $retVal);
            $this->checkExecution($retVal, $output);

            // Nettoyage des répertoires de tests d'upload
            echo "\033[0;32m-> Nettoyage des répertoires de documentations et des cartes d'identité.\033[0m" . PHP_EOL;
            foreach (['DOCUMENTATION_DIRECTORY', 'CARTE_IDENTITE_DIRECTORY'] as $envVariableName) {
                if (!empty($_ENV[$envVariableName])) {
                    $this->clearDirectory($_ENV[$envVariableName]);
                }
            }
        }

        // Démarrage des tests unitaires (juste le texte puisque cela se lance tout seul !)
        echo "\033[0;32m-> Démarrage des tests unitaires...\033[0m" . PHP_EOL . PHP_EOL;
    }

    /**
     * Supprime le contenu d'un répertoire
     * @param string $dirPath
     * @param bool $removeDir
     * @return void
     */
    private function clearDirectory(string $dirPath, bool $removeInternalDir = false): void
    {
        if (is_dir($dirPath) && false !== ($hDir = opendir($dirPath))) {
            while (false !== ($fileName = readdir($hDir))) {
                if ($fileName !== '.' && $fileName !== '..') {
                    $filePath = $dirPath . DIRECTORY_SEPARATOR . $fileName;
                    if (is_dir($filePath)) {
                        $this->clearDirectory($filePath);
                        $removeInternalDir && rmdir($filePath);
                    } else {
                        unlink($filePath);
                    }
                }
            }
            closedir($hDir);
        }
    }

    /**
     * Permet de vérifier que la commande s'est bien passée et d'afficher le résultat de la commande si celle-ci ne
     * s'est pas bien passée.
     *
     * @param int $retVal
     * @param array $output
     */
    private function checkExecution(int $retVal, array $output): void
    {
        if ($retVal !== 0) {
            foreach ($output as $line) {
                echo($line . PHP_EOL);
            }
            throw new \Exception("Erreur lors de l'initialisation des test.");
        }
    }
}
