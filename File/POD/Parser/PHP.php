<?php
namespace File\POD\Parser;

/*=head1 NAME
File\POD\Parser\PHP

=head1 DESCRIPTION
Plugin parser for POD in php. Takes into account the different comment syntax within PHP

Allows use of single-line comments ('//' or '#') or multi line comments

=head1 DEPENDENCIES
=over
=item File\POD\Parser
=back

=cut*/

class PHP extends \File\POD\Parser {
    function file_types(){
        return array('php');
    }

    function strip_comments($line){
        return preg_replace("/^(\/\*|\/\/|#)/", '', $line);
    }

    function class2file($class){
        return preg_replace("/\\\/", '/' , $class) . '.' . 'php'; 
    }
}
?>
