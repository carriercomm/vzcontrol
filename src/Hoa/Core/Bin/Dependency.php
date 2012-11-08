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
 * Class \Hoa\Core\Bin\Dependency.
 *
 * This command manipulates dependencies of a library.
 *
 * @author     Ivan Enderlin <ivan.enderlin@hoa-project.net>
 * @copyright  Copyright © 2007-2012 Ivan Enderlin.
 * @license    New BSD License
 */

class Dependency extends \Hoa\Console\Dispatcher\Kit {

    /**
     * Options description.
     *
     * @var \Hoa\Core\Bin\Dependency array
     */
    protected $options = array(
        array('no-verbose',   \Hoa\Console\GetOption::NO_ARGUMENT, 'V'),
        array('only-library', \Hoa\Console\GetOption::NO_ARGUMENT, 'l'),
        array('only-version', \Hoa\Console\GetOption::NO_ARGUMENT, 'v'),
        array('help',         \Hoa\Console\GetOption::NO_ARGUMENT, 'h'),
        array('help',         \Hoa\Console\GetOption::NO_ARGUMENT, '?')
    );



    /**
     * The entry method.
     *
     * @access  public
     * @return  int
     */
    public function main ( ) {

        $verbose = true;
        $print   = 'both';

        while(false !== $c = $this->getOption($v)) switch($c) {

            case 'V':
                $verbose = false;
              break;

            case 'l':
                $print = 'library';
              break;

            case 'v':
                $print = 'version';
              break;

            case 'h':
            case '?':
                return $this->usage();
              break;

            case '__ambiguous':
                $this->resolveOptionAmbiguity($v);
              break;
        }

        $this->parser->listInputs($library);

        if(null === $library)
            return $this->usage();

        $library = ucfirst(strtolower($library));
        $path    = 'hoa://Library/' . $library . '/composer.json';

        if(true === $verbose)
            cout('Dependency for the library ' . $library . ':');

        if(false === file_exists($path))
            throw new \Hoa\Console\Exception(
                'Not yet computed or the %s library does not exist.',
                0, $library);

        $json = json_decode(file_get_contents($path), true);

        if(true === $verbose) {

            $item      = '    • ';
            $separator = ' => ';
        }
        else {

            $item      = '';
            $separator = ' ';
        }

        foreach($json['require'] ?: array() as $dependency => $version) {

            switch($print) {

                case 'both':
                    cout($item . $dependency . $separator . $version);
                  break;

                case 'library':
                    cout($item . $dependency);
                  break;

                case 'version':
                    cout($item . $version);
                  break;
            }
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

        cout('Usage   : core:dependency <options> library');
        cout('Options :');
        cout($this->makeUsageOptionsList(array(
            'V'    => 'No-verbose, i.e. be as quiet as possible, just print ' .
                      'essential informations.',
            'l'    => 'Print only the library name.',
            'v'    => 'Print only the version.',
            'help' => 'This help.'
        )));

        return;
    }
}

}

__halt_compiler();
Manipulate dependencies of a library.
