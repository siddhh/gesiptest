<?php

namespace App\Dql;

use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\AST\Functions\FunctionNode;

/**
 * Fonction DQL permettant d'extraire une valeur dans un tableau json et d'appliquer une condition dessus.
 *  JsonGet(ArithmeticPrimary, StringPrimary, [StringPrimary])
 * Si la valeur du json est une date, nous pouvons passer un troisième paramètre de la fonction avec le format de cette
 *  date dans le SGBD.
 * Exemple : $qr->andWhere("JSON_GET(h.donnees, 'dateLimite', 'DD/MM/YYYY') >= :uneDate")
 *            ->setParameter('uneDate', '2020-11-06');
 */
class JsonGet extends FunctionNode
{

    public $firstExpression = null;
    public $secondExpression = null;
    public $thirdExpression = null;

    /**
     * Parse le DQL pour récupérer les arguments passé à la fonction
     */
    public function parse(Parser $parser)
    {
        $lexer = $parser->getLexer();
        $parser->match(Lexer::T_IDENTIFIER);
        $parser->match(Lexer::T_OPEN_PARENTHESIS);
        $this->firstExpression = $parser->ArithmeticPrimary();
        $parser->match(Lexer::T_COMMA);
        $this->secondExpression = $parser->StringPrimary();
        // Le troisième paramètre n'est pas obligatoire
        if (Lexer::T_COMMA === $lexer->lookahead['type']) {
            $parser->match(Lexer::T_COMMA);
            $this->thirdExpression = $parser->StringPrimary();
        }
        $parser->match(Lexer::T_CLOSE_PARENTHESIS);
    }

    /**
     * Génère le code SQL correspondant
     */
    public function getSql(SqlWalker $sqlWalker)
    {
        // Si nous avons un format de date passé en 3ème paramètre de la fonction, alors on formate la valeur du json
        //  comme décrit par le paramètre.
        if ($this->thirdExpression !== null) {
            return 'to_date(' . $this->firstExpression->dispatch($sqlWalker) . '::jsonb->>'
                . $this->secondExpression->dispatch($sqlWalker) . ',' . $this->thirdExpression->dispatch($sqlWalker) . ')';
        }

        // Sinon, on traite direct sans formattage
        return $this->firstExpression->dispatch($sqlWalker) . '::jsonb->>' . $this->secondExpression->dispatch($sqlWalker);
    }
}
