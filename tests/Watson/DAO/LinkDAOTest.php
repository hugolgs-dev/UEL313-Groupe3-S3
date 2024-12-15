<?php

// Tests unitaires pour la classe LinkDAO
// @author Hugo Rytlewski
// @license Personnel - Création dans le cadre de la licence
// @date Décembre 2024

namespace Tests\Watson\DAO;

use PHPUnit\Framework\TestCase;
use Watson\DAO\LinkDAO;
use Watson\Domain\Link;
use Watson\Domain\User;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Classe de test pour LinkDAO
 * Vérifie les opérations de base de données sur les liens
 * Utilise des mocks pour simuler la couche de données
 */
class LinkDAOTest extends TestCase
{
    // Instance de LinkDAO à tester
    private LinkDAO $linkDAO;
    
    // Mock de la connexion à la base de données
    private Connection&MockObject $dbMock;

    /**
     * Initialisation avant chaque test
     * Configure les mocks nécessaires et prépare l'environnement de test
     */
    protected function setUp(): void
    {
        // Création d'un mock pour la connexion base de données
        // Simule les interactions avec Doctrine DBAL
        $this->dbMock = $this->createMock(Connection::class);
        
        // Initialisation du DAO avec la connexion mockée
        $this->linkDAO = new LinkDAO($this->dbMock);

        // Création des mocks pour les DAO dépendants
        // Nécessaire pour simuler les relations entre entités
        $userDAOMock = $this->createMock(\Watson\DAO\UserDAO::class);
        $tagDAOMock = $this->createMock(\Watson\DAO\TagDAO::class);
        
        // Configuration des données factices pour les mocks
        // Simule un utilisateur avec ID=1 et une liste vide de tags
        $user = new User();
        $user->setId(1);
        $userDAOMock->method('find')->willReturn($user);
        $tagDAOMock->method('find')->willReturn([]);

        // Injection des DAOs mockés dans le LinkDAO
        $this->linkDAO->setUserDAO($userDAOMock);
        $this->linkDAO->setTagDAO($tagDAOMock);
    }

    /**
     * Test de la méthode findLast15
     * Vérifie que la méthode retourne les 15 liens les plus récents
     * dans le bon ordre (du plus récent au plus ancien)
     */
    public function testFindLast15()
    {
        // Préparation des données de test
        // Création de 20 liens factices pour simuler la base de données
        $testData = [];
        for ($i = 20; $i >= 1; $i--) {
            $testData[] = [
                'lien_id' => $i,
                'lien_titre' => "Titre $i",
                'lien_url' => "http://exemple.com/$i",
                'lien_desc' => "Description $i",
                'user_id' => 1
            ];
        }

        // Configuration du mock de base de données
        // Vérifie que la requête SQL est correctement formatée
        $expectedQuery = "\n SELECT * \n FROM tl_liens \n ORDER BY lien_id DESC \n LIMIT 15\n";
        
        // Configuration des attentes du mock
        // - Vérifie que fetchAll est appelé une seule fois
        // - Vérifie que la requête SQL correspond exactement à celle attendue
        // - Retourne les 15 premiers liens des données de test
        $this->dbMock->expects($this->once())
            ->method('fetchAll')
            ->with($this->callback(function($query) use ($expectedQuery) {
                // Normalisation des espaces pour la comparaison
                $normalizedQuery = preg_replace('/\s+/', ' ', trim($query));
                $normalizedExpected = preg_replace('/\s+/', ' ', trim($expectedQuery));
                return $normalizedQuery === $normalizedExpected;
            }))
            ->willReturn(array_slice($testData, 0, 15));

        // Exécution de la méthode testée
        $result = $this->linkDAO->findLast15();

        // Vérifications des résultats
        
        // 1. Vérifie le nombre exact de liens retournés
        $this->assertCount(15, $result, "Le nombre de liens retournés devrait être 15");
        
        // 2. Vérifie l'ordre des liens (du plus récent au plus ancien)
        $previousId = 21;  // ID de départ (plus grand que le plus grand ID de test)
        foreach ($result as $link) {
            // Vérifie le type de chaque élément
            $this->assertInstanceOf(Link::class, $link);
            
            // Vérifie que les IDs sont décroissants
            $this->assertLessThan(
                $previousId, 
                $link->getId(), 
                "Les liens devraient être ordonnés par ID décroissant"
            );
            $previousId = $link->getId();
        }
        
        // 3. Vérifie les limites de la plage retournée
        $firstLink = reset($result);
        $lastLink = end($result);
        
        // Vérifie l'ID du premier lien (le plus récent)
        $this->assertEquals(
            20, 
            $firstLink->getId(), 
            "Le premier lien devrait avoir l'ID le plus élevé"
        );
        
        // Vérifie l'ID du dernier lien (le plus ancien des 15)
        $this->assertEquals(
            6, 
            $lastLink->getId(), 
            "Le dernier lien devrait avoir l'ID le plus bas des 15"
        );
    }
}