<?php
namespace File\POD\Parser;

/*=head1 NAME
File\POD\Parser\Js

=head1 DESCRIPTION
Plugin parser for POD in Javascript. Takes into account the different comment syntax within PHP

Allows use of single-line or multi line comments,

=head1 DEPENDENCIES
=over
=item File\POD\Parser
=back

=cut */

class Js extends \File\POD\Parser {
    function file_types(){
        return array('js');
    }

    function strip_comments($line){
        return preg_replace("/^(\/\/|\/\*)/", '', $line);
    }

    function class2file($class){
        return preg_replace("/\\./", '/' , $class) . '.' . 'js'; 
    }
}
?>
