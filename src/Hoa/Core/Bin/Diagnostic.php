<?php

/**
 * Hoa
 *
 *
 * @license
 *
 * New BSD License
 *
 * Copyright © 2007-2012, Ivan Enderlin. All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *     * Redistributions of source code must retain the above copyright
 *       notice, this list of conditions and the following disclaimer.
 *     * Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *     * Neither the name of the Hoa nor the names of its contributors may be
 *       used to endorse or promote products derived from this software without
 *       specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDERS AND CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

namespace Hoa\Core\Bin {

/**
 * Class \Hoa\Core\Bin\Diagnostic.
 *
 * This command generates a diagnostic.
 *
 * @author     Ivan Enderlin <ivan.enderlin@hoa-project.net>
 * @author     Julien Clauzel <julien.clauzel@hoa-project.net>
 * @copyright  Copyright © 2007-2012 Ivan Enderlin, Julien Clauzel.
 * @license    New BSD License
 */

class Diagnostic extends \Hoa\Console\Dispatcher\Kit {

    /**
     * Options description.
     *
     * @var \Hoa\Core\Bin\Diagnostic array
     */
    protected $options = array(
        array('section', \Hoa\Console\GetOption::REQUIRED_ARGUMENT, 's'),
        array('mail',    \Hoa\Console\GetOption::REQUIRED_ARGUMENT, 'm'),
        array('help',    \Hoa\Console\GetOption::NO_ARGUMENT,       'h'),
        array('help',    \Hoa\Console\GetOption::NO_ARGUMENT,       '?')

    );



    /**
     * The entry method.
     *
     * @access  public
     * @return  int
     */
    public function main ( ) {

        $sections   = array();
        $mail       = null;
        $diagnostic = array();

        while(false !== $c = $this->getOption($v)) switch ($c) {

            case 's':
                $sections = $this->parser->parseSpecialValue($v);
              break;

            case 'm':
                $mail = $v;
              break;

            case 'h':
            case '?':
                return $this->usage();
              break;

            case '__ambiguous':
                $this->resolveOptionAmbiguity($v);
              break;
        }

        $store = function ( $sections, $key, $value = null ) use ( &$diagnostic ) {

            if(is_array($key) && null === $value)
                foreach($key as $i => $name)
                    $diagnostic[$sections][$i] = $name;
            else
                $diagnostic[$sections][$key] = $value;

            return;
        };

        $store(
            'version',
            'hoa',
            HOA_VERSION_MAJOR . '.' . HOA_VERSION_MINOR . '.' .
            HOA_VERSION_RELEASE . HOA_VERSION_STATUS . '-' . HOA_VERSION_EXTRA
        );
        $store(
            'version',
            'php',
            phpversion()
        );
        $store(
            'version',
            'zend_engine',
            zend_version()
        );
        $store(
            'system',
            'platform',
            php_uname()
        );
        $store(
            'system',
            'architecture',
            (true === S_32_BITS) ? '32bits' : '64bits'
        );
        $store(
            'system',
            'lang',
            $_SERVER['LANG']
        );
        $store(
            'bin',
            'self',
            $_SERVER['PHP_SELF']
        );
        $store(
            'bin',
            'hoa',
            \Hoa\Core::getInstance()->getParameters()->getFormattedParameter('root.hoa')
        );
        $store(
            'bin',
            'php_dir',
            PHP_BINDIR
        );
        $store(
            'bin',
            'php',
            !defined('PHP_BINARY') ? 'unknown' : PHP_BINARY
        );

        foreach(get_loaded_extensions() as $extension) {

            $reflection = new \ReflectionExtension($extension);
            $entry      = 'extension-' . strtolower($extension);

            if(    'extension-standard' !== $entry
                && 'extension-core'     !== $entry) {

                $entries = array();

                foreach($reflection->getINIEntries() as $key => $value)
                    $entries[substr($key, strpos($key, '.') + 1)] = $value;
            }
            else
                $entries = $reflection->getINIEntries();

            $store(
                $entry,
                'version',
                $reflection->getVersion() ?: 'unknown'
            );
            $store(
                $entry,
                $entries
            );
        }

        if(empty($sections) || in_array('all', $sections))
            $ini = $this->arrayToIni($diagnostic);
        else {

            $handle = array();

            foreach($sections as $section) {

                if(false === array_key_exists($section, $diagnostic))
                    return 1;

                $handle[$section] = $diagnostic[$section];
            }

            $ini = $this->arrayToIni($handle);
        }

        cout($ini);

        if(null !== $mail) {

            $subject = 'Diagnostic from ' . get_current_user();

            return mail($mail, $subject, $ini) ? 0 : 1;
        }

        return;
    }

    /**
     * The command usage.
     *
     * @access  public
     * @return  int
     */
    public function usage ( ) {

        cout('Usage   : core:diagnostic <options>');
        cout('Options :');
        cout($this->makeUsageOptionsList(array(
            's'    => 'Sections (comma separated) to display, among:' . "\n" .
                      '    • all;' . "\n" .
                      '    • version;' . "\n" .
                      '    • system;' . "\n" .
                      '    • bin;' . "\n" .
                      '    • extension-<name in lowercase> (see `php -m`).',
            'm'    => 'Email address where to send the diagnostic.',
            'help' => 'This help.'
        )));

        return;
    }

    /**
     * Transform an array into INI format.
     *
     * @access  public
     * @param   array  $array    Array to transform.
     * @return  string
     */
    private function arrayToIni ( Array $array ) {

        $out = null;

        foreach($array as $section => $entries) {

            if(null !== $out)
                $out .= "\n\n";

            $out .= '[' . $section . ']';

            foreach($entries as $key => $value) {

                if (is_array($value))
                    $value = implode(' ', $value);

                $out .= "\n" . $key . ' = "' . $value . '"';
            }
        }

        return $out;
    }
}

}

__halt_compiler();
Generate a diagnostic for help.
