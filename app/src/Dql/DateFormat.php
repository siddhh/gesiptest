<?php

namespace App\Dql;

use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\AST\Functions\FunctionNode;

/**
 * Fonction DQL retournant 1
 *  dateformat(Colonne, Format)
 */
class DateFormat extends FunctionNode
{

    /**
     * Holds the timestamp of the DATE_FORMAT DQL statement
     * @var $dateExpression
     */
    protected $dateExpression;

    /**
     * Holds the '% format' parameter of the DATE_FORMAT DQL statement
     * var String
     */
    protected $formatChar;

    public function getSql(SqlWalker $sqlWalker)
    {
        return 'to_char (' . $sqlWalker->walkArithmeticExpression($this->dateExpression) . ',' . $sqlWalker->walkStringPrimary($this->formatChar) . ')';
    }

    public function parse(Parser $parser)
    {
        $parser->Match(Lexer::T_IDENTIFIER);
        $parser->Match(Lexer::T_OPEN_PARENTHESIS);

        $this->dateExpression = $parser->ArithmeticExpression();
        $parser->Match(Lexer::T_COMMA);

        $this->formatChar = $parser->ArithmeticExpression();

        $parser->Match(Lexer::T_CLOSE_PARENTHESIS);
    }
}
