<?php
namespace File\POD;

/*=head1 NAME
File\POD\Parser

=head1 DESCRIPTION
A default parser for extracting POD from files. Also acts as a base class for other parser plugins
Class is required by File\POD class

=head1 DEPENDENCIES
=over
=item File\POD
=back

=head1 Object Methods

In order to successfully subclass and create parser plugins for new filetypes, you can inherit form this class and override any or all of the following methods.

=cut*/
class Parser {

/*=head2 file_types()
returns a list of strings representing the file extensions of the file types supported by the parser
=cut*/
    function file_types(){
        return array();
    }

/*=head2 is_instruction($line)
Parses the $line provided as an argument and decides whether that line constitutes a POD instruction.
If so, returns a list where the first index is the POD instruction and the second index is the rest of the line.
If not, returns null.
=cut*/
    function is_instruction($line){
        if (preg_match("/^=(\w+)\s*(.*)?\s*$/", $line, $matches)){
            return array($matches[1], $matches[2]);
        }
        return null;
    }

/*=head2 strip_comments($line)
Given $line, strips potential single line comment syntax and returns the cleaned string.
=cut*/
    function strip_comments($line){
        return $line;
    }
/*=head2 class2file($class)
Given a class name, should return a file path fragment, including file extension, of the file expected to contain that class.
=cut*/
    function class2file($class){
        return $class; 
    }
}
?>
