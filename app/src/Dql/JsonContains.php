<?php

namespace App\Dql;

use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\AST\Functions\FunctionNode;

/**
 * Fonction DQL retournant 1
 *  JSON_CONTAINS(ArithmeticPrimary, StringPrimary)
 */
class JsonContains extends FunctionNode
{

    public $firstExpression = null;
    public $secondExpression = null;

    /**
     * Parse le DQL pour récupérer les arguments passé à la fonction
     */
    public function parse(Parser $parser)
    {
        $parser->match(Lexer::T_IDENTIFIER);
        $parser->match(Lexer::T_OPEN_PARENTHESIS);
        $this->firstExpression = $parser->ArithmeticPrimary();
        $parser->match(Lexer::T_COMMA);
        $this->secondExpression = $parser->StringPrimary();
        $parser->match(Lexer::T_CLOSE_PARENTHESIS);
    }

    /**
     * Génère le code SQL correspondant
     */
    public function getSql(SqlWalker $sqlWalker)
    {
        // On fabrique la query :
        // (On rajoute ' AND 1' à la fin car Doctrine rajoute ' = 1')
        return $this->firstExpression->dispatch($sqlWalker) . '::jsonb'
            . ' @> '
            . $this->secondExpression->dispatch($sqlWalker) . '::jsonb AND 1';
    }
}
