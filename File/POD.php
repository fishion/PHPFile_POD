<?php
namespace File;
require_once('File.php');
require_once('File/POD/Parser.php');
/*=head1 NAME
File\POD

=head1 DESCRIPTION
A subblass of File, which enables parsing of POD (Plain Old Documentation) from the source files of a number of languages.

POD is the primary form of documentation in Perl, and treated as comments by the Perl compiler, but there's no reason not to
apply it to other languages within that language's standard comment blocks.

Once the POD has been extracted form the source, it can be output in any number of formats. The nost obvious one being HTML.

=head1 DEPENDENCIES
=over
=item File
=back

=head1 Synopsis

 require_once('File/POD.php');
 $podob = new File\POD($file);
 echo $podob->pod2html();

=cut*/

class POD extends \File {
    private $parsers = array();
    private $parser;
    private $nesting = array();
    private $dependencies;
    private $classname;
    private $instructions;

/*=head1 Constructor

  new File\POD($file=null);

Takes a single opional string argument indicating the file on which to operate.
This can be set or changed after construction using the filename() method.

=cut*/
    function __construct($file=null){
        # set up object
        parent::__construct();

        # Add default parser
        $this->parsers['Default'] = new POD\Parser();
        
        # find all installed POD parsers plugins
        foreach ($this->include_paths() as $inc_dir){
            $parserdir = $inc_dir . '/File/POD/Parser';
            if (is_dir($parserdir)){
                $files = scandir($parserdir);
                foreach ($files as $parser){
                    if (preg_match("/(.*)\.php$/", $parser, $matches)){
                        require_once($parserdir . '/' . $parser);
                        # for some reason this won'r accept relative class path. Meh.
                        $classname = "\\File\\POD\\Parser\\" . $matches[1];
                        $parser_ob = new $classname();
                        foreach ($parser_ob->file_types() as $ext){
                            $this->parsers[strtolower($ext)] = $parser_ob;
                        }
                    }
                }
            }
        }
        if (!$this->parsers){
            throw new \Exception("No POD parsers found");
        }

        if (is_array($file)){
            $this->set_parser($file['extension']);
            $file = $file['dir'] . $this->parser->class2file($file['class']);
        }
        if ($file){
            $this->filename($file);
        }
    }

/*=head1 Object Methods

=head2 filename($name=null)
Get/Set the file on which oher methods will operate. See 'File' documentation for more details.
=cut*/
    function filename($name=null){
        if ($name) {
            //changing file will therefore require re-parsing, potentially with a new parser
            $this->instructions = null;
            $this->parser       = null;
            $this->classname    = null;
            $this->dependencies = null;
        }
        return parent::filename($name);
    }

/*=head2 get_parser($refresh=null)
Get a suitable parser plugin for the current file, based on it's file type.
Providing a true value as an argument forces a refresh of the allocated parser.
=cut*/
    function get_parser($refresh = null){
        if ($refresh || !$this->parser){
            preg_match("/.*\.(\w+)$/", $this->filename(), $matches);
            $this->set_parser($matches[1]);
        }
        return $this->parser;
    }
/*=head2 set_parser($type)
Set the parser to a specific type by passing in a file extension supported by an installed parser plugin
=cut*/
    function set_parser($type){
        $this->parser = array_key_exists(strtolower($type), $this->parsers) ?
            $this->parsers[strtolower($type)] : $this->parsers['Default'];
    }

/*=head2 parse()
Parses any POD out of the source code, and returns the docs as an instruction list.
=cut*/
    function parse(){
        if (!$this->is_file()){
            return;
        }
        $file = fopen( $this->fullpath(), 'r' )
                    or exit("unable to open '$this->filename()'");
        $this->get_parser();

        $in_pod = false;
        $new_paragraph = false;
        $instructions = array();

        while (!feof($file)){
            $line = $this->parser->strip_comments( fgets($file) );
            if ($matches = $this->parser->is_instruction($line)){
                $in_pod = ($matches[0] == 'cut') ? false : true;
                if (!$in_pod){ continue; }
                array_push($instructions, array('element' => $matches[0], 'title' => $matches[1], 'content' => array()));
                $new_paragraph = true;
            } else if ($in_pod && preg_match("/^\s*$/", $line)){
                $new_paragraph = true;
            } else if ($in_pod && $new_paragraph){
                array_push($instructions[count($instructions) - 1]['content'], $line);
                $new_paragraph = false;
            } else if ($in_pod && !$new_paragraph){
                $last_inst_index = count($instructions) - 1;
                $last_content_index = count($instructions[$last_inst_index]['content']) - 1;
                $instructions[$last_inst_index]['content'][$last_content_index] .= $line;
            }
        }

        fclose($file);
        $this->instructions = $instructions;
        return $this->instructions;
    }

/*=head2 pod2html()

Renders the parsed POD instruction set as HTML and returns the HTML string.

Performs the parsing step initially if necessary too.

Takes a single, optionsl $options associative array which can contain the following keys:

=over
=item nocontents
If this key exists, the generated contents links at the top of the HTML are ommitted.
=back

=cut*/
    function pod2html($options=array()){
        if (!$this->instructions){ $this->parse(); }
        $instructions = $this->instructions;
        $out = '';
        $index = -1;
        $contents = '<h1>CONTENTS</h1>';
        $content_indent = 0;
        foreach($instructions as $inst){
            $index++;
            $hlevel = 1;
            if (preg_match("/head(\d)/", $inst['element'], $hlevel)){
                $out .= '<h' . $hlevel[1] . ' id="POD_' . htmlentities($inst['title']) . '">' . htmlentities($inst['title']) . '</h' . $hlevel[1] . '>';
                while ($content_indent != $hlevel[1]){
                    if ($content_indent < $hlevel[1]){
                        $contents .= "<ul>\n";
                        $content_indent++;
                    } else {
                        $contents .= "</ul>\n";
                        $content_indent--;
                    }
                }
                $contents .= '<li><a href="#POD_'. htmlentities($inst['title']) .'">'. htmlentities($inst['title']) .'</a></li>';
            } else if ($inst['element'] == 'over'){
                // look forward a little and choose dl or ul
                $this->add_to_nesting(
                    ($instructions[$index+1]['element'] == 'item' &&
                     $instructions[$index+1]['title'] &&
                     $instructions[$index+1]['content']) ? 'dl' : 'ul');
                $out .= '<' . $this->last_nested() . '>';
            } else if ($inst['element'] == 'back'){
                if ($this->last_nested() == 'dd' || $this->last_nested() == 'li'){
                    $out .= '</' . $this->pop_nested() . '>';
                }
                if ($this->last_nested() == 'dl' || $this->last_nested() == 'ul'){
                    $out .= '</' . $this->pop_nested() . '>';
                }
            } else if ($inst['element'] == 'item'){
                if ($this->last_nested() == 'dd' || $this->last_nested() == 'li'){
                    $out .= '</' . $this->pop_nested() . '>';
                }
                if ($this->last_nested() == 'dl'){
                    $out .= '<dt>' . htmlentities($inst['title']) . '</dt>';
                    $out .= '<dd>';
                    $this->add_to_nesting('dd');
                } else if ($this->last_nested() == 'ul'){
                    $out .= '<li>' . htmlentities($inst['title']); 
                    $this->add_to_nesting('li');
                }
            } else if ($inst['element']) {
                $out .= $inst['element'] . htmlentities($inst['title']);
            }

            if($inst['content']){
                $out .= $this->content2html($inst['content']);
            }
        }
        while ($content_indent-- > 0){
            $contents .= '</ul>';
        }
        return (array_key_exists('nocontents', $options)) ? $out : $contents . $out;
    }

/*=head2 class()
Get the classname from the POD
=cut*/
    function classname(){
        if (!$this->classname){
            if (!$this->instructions){ $this->parse(); }
            foreach ($this->instructions as $inst){
                if (preg_match("/^head/i", $inst['element']) &&
                    strtolower($inst['title']) == 'name') {
                    return $inst['content'][0];
                }
            }
        }
        return $this->classname;
    }

/*=head2 dependencies($paths=array(), $ignore=array())

If the POD has a 'dependencies' heading, it is expected to contain a list of dependencies, which will be returned as a list by this method.

Takes two lists as arguments
=over
=item $paths=array()
A list of subpaths relative to an include path where your files may be lurking
=item $ignore=array()
An associative array, the keys for which consist of filename which should be ignored as dependencies. This is primarily to make recurrsion easier without infinite looping.
=back

=cut*/

    function dependencies($paths=array(), $ignore=array()){
        if (!$this->dependencies){
            if (!$this->instructions){ $this->parse(); }
            $this->dependencies = array();
        
            $indep = False;
            foreach ($this->instructions as $inst){
                if ($indep == False &&
                          preg_match("/^head/i", $inst['element']) &&
                          strtolower($inst['title']) == 'dependencies') {
                    $indep = True;
                } elseif ($indep == True &&
                          ( $inst['element'] == 'back' ||
                            preg_match("/^head/i", $inst['element']) ) ){
                    $indep = False;
                } elseif ($indep == True && $inst['element'] == 'item'){
                    $dep = $inst['title'];
                    $dep = preg_replace("/\s/", '', $dep);
                    if (array_key_exists($dep, $ignore) || strtolower($dep) == 'none'){
                        continue; # we'll have no infinite recurrsion here.
                    }
                    $ignore[$dep] = 1;
                    array_push($this->dependencies, array('name' => $dep));
                    # find the file
                    foreach ($paths as $path){
                        $dpo = new POD(array('dir' => $path,
                                             'class' => $dep,
                                             'extension' => $this->extension()));
                        if (!$dpo->is_file()){continue;}
                        $this->dependencies[count($this->dependencies) - 1]['path'] = $path; 
                        $this->dependencies = array_merge($this->dependencies,
                                                          $dpo->dependencies($paths, $ignore) );
                    }
                }
            }
        }
        return $this->dependencies;
    }

/*=head2 find_pod($basedir, $dir='')

Given a base directory and an optional relative path within that, will recursively scan to find
files containing POD documentation.

Returns a list of pod objects found.

=cut*/

    function find_pod($basedir, $dir=''){
        $basedir = (preg_match("/\/$/", $basedir)) ? $basedir : $basedir . '/';
        $dir = ($dir=='' || preg_match("/\/$/", $dir)) ? $dir : $dir . '/';

        $contents = scandir($basedir . $dir);
        if (!$contents){
            return;
        }

        $objects = array();
        foreach ($contents as $file){
            if (preg_match("/^\.+/", $file)) { # ignore . and .. and hidden .files
                continue;
            }
            if (is_dir($basedir . $dir . $file)){
                $recurse = $this->find_pod($basedir, $dir . $file);
                if (count($recurse)){
                    $objects = array_merge($objects, $recurse);
                }
            } else {
                $ob = new POD($basedir . $dir . $file);
                if (count($ob->parse())){
                    array_push($objects, $ob);
                }
            }
        }
        usort($objects, function($a, $b){
            if ($a->filename() == $b->filename()){
                return 0;
            }
            return ($a->filename() < $b->filename()) ? -1 : 1;
        });
        return $objects;
    }


    /* Private Functions */
    
    private function content2html($content){
        $bit = '';
        foreach($content as $para){
            if (preg_match("/^\s/", $para)){
                $bit .= '<code>' . htmlentities($para) . '</code>';
            } else {
                $bit .= '<p>' . preg_replace("/\n/", '<br>', htmlentities($para)) . '</p>';
            }
        }
        return $bit;
    }
    private function add_to_nesting($el){
        array_push($this->nesting, $el);
    }
    private function last_nested(){
        if (!count($this->nesting)) { return ''; }
        return $this->nesting[count($this->nesting) - 1];
    }
    private function pop_nested(){
        return array_pop($this->nesting);
    }
}
?>
