<?php

/**
 * 
 * Copyright (c) 2012 Kevin Gravier <kevin@mrkmg.com>
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell 
 * copies of the Software, and to permit persons to whom the Software is furnished
 * to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED,
 * INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A 
 * PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
 * OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE
 * SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *
 */


function putLine($line){
    echo $line.PHP_EOL;
    return true;
}

function autocompleterParse($pre,$cur){
    global $servers;
    global $function_mapping;

    if(strlen($pre)){
        $pCurrent = explode(' ',$pre);
        $func = $pCurrent[0];
        if(!isset($function_mapping[$func]['auto'])){
            return array();
        }
        $auto_str = $function_mapping[$func]['auto'];
        $autos = explode(' ',$auto_str);
        $pres = explode(' ',$pre);
        array_shift($pres);
        if(!isset($autos[count($pres)-1])) return array();
        $type = $autos[count($pres)-1];
        switch(substr($type,0,1)){
            case '$':
                $var = substr($type,1);
                if($var == 'host') $to_check = array_keys($servers);
                elseif($var == 'ctid') $to_check = getCtidFor($pres[count($pres)-2]);
                elseif($var == 'template') $to_check = getOnlinetemplates(null);
                break;
            case '?':
                $to_check = explode(',',substr($type,1));
                break;
        }
    }else{
        $to_check = array_keys($function_mapping);
    }

    $curlen = strlen($cur);
    $matches = array();
    foreach($to_check as $item)
        if(substr($item,0,$curlen) == $cur) $matches[] = $item;

    return $matches;

}

function getCtidFor($server_name){
    global $servers;

    $ctid_raw = returnSSH($server_name,'vzlist -a -H -1');
    $ctids = explode("\n",$ctid_raw);
    array_walk($ctids,function(&$i){ return $i = trim($i); });
    array_pop($ctids);
    return $ctids;
}

function putHeader($header){
    putLine($header);
    putLine(str_repeat('-', strlen($header)));
    return true;
}

function runSSH($server_name,$command){
    global $servers;
    global $reader;
    if(!isset($servers[$server_name])) return false;
    $reader->restoreStty();
    $command = 'ssh -q -t'
             . (isset($servers[$server_name]['port'])?' -p '.$servers[$server_name]['port']:'')
             . ' -o ConnectTimeout=2 root@'
             . $servers[$server_name]['host']
             . ' "'.str_replace('"','\\"',$command).'"';
    passthru($command,$return);
    $reader->setStty();
    return !$return;
}

function returnSSH($server_name,$command){
    global $servers;
    if(!isset($servers[$server_name])) return false;
    $command = 'ssh -q '
             . (isset($servers[$server_name]['port'])?' -p '.$servers[$server_name]['port']:'')
             . ' -o ConnectTimeout=2 root@'
             . $servers[$server_name]['host']
             . ' "'.str_replace('"','\\"',$command).'"';
    $output = shell_exec($command);
    return $output;
}

function list_servers($args,$all=false){
    global $servers;
    
    $servers_wanted = get_wanted_servers($args);
    if(!$servers_wanted) return false;

    foreach($servers_wanted as $server_name){
        putHeader('Listing for '.$server_name);
        if(!runSSH($server_name,'vzlist'.($all?' -a':''))){
            putLine($server_name.' is not online');
        }
        putLine('');
    }
    return true;
}

function list_all_servers($args){
    return list_servers($args,true);
}

function move_container($args,$live=false){
    global $servers;

    $args = explode(' ',$args);
    if(count($args) !== 3){
        putLine('Incorrect usage.');
        return false;
    }
    $source = $args[0];
    $ctid = $args[1];
    $dest = $args[2];

    if(!isset($servers[$source])){
        putLine($source.' not found.');
        return false;
    }
    elseif(!isset($servers[$dest])){
        putLine($dest.' not found.');
        return false;
    }

    putLine('Sending move command');
    runSSH($source,'vzmigrate'.($live?' --online ':' ').$servers[$dest]['host'].' '.$ctid);
    return true;
}

function move_container_online($args){
    return move_container($args,true);
}

function stop_container($args){
    global $servers;
    $args = explode(' ',$args);
    $host = $args[0];
    $ctid = $args[1];

    if(!isset($servers[$host])){
        putLine($host.' is not known');
        return false;
    }

    runSSH($host,'vzctl stop '.$ctid);
    return true;
}

function start_container($args){
    global $servers;
    $args = explode(' ',$args);
    $host = $args[0];
    $ctid = $args[1];

    if(!isset($servers[$host])){
        putLine($host.' is not known');
        return false;
    }

    runSSH($host,'vzctl start '.$ctid);

    return true;
}

function restart_container($args){
    global $servers;
    $args = explode(' ',$args);
    $host = $args[0];
    $ctid = $args[1];

    if(!isset($servers[$host])){
        putLine($host.' is not known');
        return false;
    }

    runSSH($host,'vzctl restart '.$ctid);

    return true;
}

function enter_container($args){
    global $servers;
    $args = explode(' ',$args);
    $host = $args[0];
    $ctid = $args[1];

    if(!isset($servers[$host])){
        putLine($host.' is not known');
        return false;
    }

    runSSH($host,'vzctl enter '.$ctid);

    return true;
}

function create_container($args){
    global $servers;
    global $reader;
    $args = trim($args);
    if(!isset($servers[$args])){
        putLine($args.' is not known');
        return false;
    }
    global $___HOST;
    $___HOST = $args;

    $_oldAuto = $reader->getAutocomplete();
    $reader->removeAutocomplete();
    putLine('You will be prompted for a series of details.');
    $ctid = $reader->readLine('CTID? ');
    $reader->setAutocomplete(function($pre,$cur){
        global $___HOST;
        $templates = get_templates_for_host($___HOST);
        $curlen = strlen($cur);
        $matches = array();
        foreach($templates as $item)
            if(substr($item,0,$curlen) == $cur) $matches[] = $item;

        return $matches;
    });
    $ostemplate = $reader->readLine('OS Template? ');
    $reader->removeAutocomplete();
    $ipaddr = $reader->readLine('IP Address? ');
    $hostname = $reader->readLine('Hostname? ');
    $nameserver = $reader->readLine('Nameserver? ');
    $pReader = new Hoa\Console\Readline\Password;
    $tries = 0;
    do{
        $rootPassword    =  $pReader->readLine('Root Password? ');
        $confirmPassword =  $pReader->readLine('Confirm? ');
        $tries++;
    }while($tries <= 3 and !((!empty($rootPassword) and $rootPassword == $confirmPassword) or !putLine('Passwords did not match')));
    if($tries > 3){
        putLine('Quiting, password did not match');
        return false;
    }
    putLine('Going to create a new container on '.$args);
    putLine('CTID: '.$ctid);
    putLine('OS Template: '.$ostemplate);
    putLine('IP Address: '.$ipaddr);
    putLine('Hostname: '.$hostname);
    putLine('Nameserver: '.$nameserver);
    $confirm = $reader->readLine('Are you sure you want to create this container? (y/N)> ');
    if(!in_array($confirm,array('y','Y','yes','Yes','YES'))){
        return true;
    }
    $reader->setAutocomplete($_oldAuto);
    putLine('Creating container');
    if(!runSSH($args,'vzctl create '.$ctid.' --ostemplate '.$ostemplate))
    {
        putLine('Failed to create container');
        return true;
    }
    runSSH($args,'vzctl set '.$ctid.' --ipadd '.$ipaddr.' --save');
    runSSH($args,'vzctl set '.$ctid.' --nameserver '.$nameserver.' --save');
    runSSH($args,'vzctl set '.$ctid.' --hostname '.$hostname.' --save');
    runSSH($args,'vzctl set '.$ctid.' --userpasswd root:'.$rootPassword.' --save');

    return true;
}

function destroy_container($args){
    global $servers;
    global $reader;
    $args = explode(' ',$args);
    $host = $args[0];
    $ctid = $args[1];

    if(!isset($servers[$host])){
        putLine($host.' is not known');
        return false;
    }


    $confirm = $reader->readLine('Are you sure you want to destroy '.$ctid.'? (y/N) ');
    if(!in_array($confirm,array('y','Y','yes','Yes','YES'))){
        return true;
    }

    runSSH($host,'vzctl destroy '.$ctid);

    return true;
}

function list_templates($args){
    global $servers;

    $servers_wanted = get_wanted_servers($args);
    if(!$servers_wanted) return false;

    foreach($servers_wanted as $server_name){
        putHeader('Listing Templates for '.$server_name);
        runSSH($server_name,'ls /vz/template/cache | sed s/.tar.gz//');
        putLine('');
    }

    return true;
}

function get_templates_for_host($host){
    global $servers;

    $templates = returnSSH($host,'ls /vz/template/cache | sed s/.tar.gz//');
    $templates = explode("\n",$templates);

    return $templates;
}

function uptime($args){
    global $servers;

    $servers_wanted = get_wanted_servers($args);
    if(!$servers_wanted) return false;

    foreach($servers_wanted as $server_name){
        putHeader('Uptime for '.$server_name);
        runSSH($server_name,'uptime');
        putLine('');
    }

    return true;
}

function get_wanted_servers($args){
    global $servers;
    if(strlen($args)){
        $servers_wanted = explode(' ',$args);
        $servers_wanted = array_filter($servers_wanted,function($v){return !empty($v); });
        foreach($servers_wanted as $server_name){
            if(!isset($servers[$server_name])){
                putLine($server_name.' is not known');
                putLine('');
                return false;
            }
        }
    }
    else{
        $servers_wanted = array_keys($servers);
    }
    return $servers_wanted;
}

function list_online_templates($args){
    $url = 'download.openvz.org';
    $folder = 'template/precreated/';
    if(strlen($args)) $folder .= $args.'/';
    $conn = ftp_connect($url);
    $log = ftp_login($conn, 'anonymous','anonymous');
    $file_list = ftp_nlist($conn, $folder);
    $file_list = array_filter($file_list,function($o){ return preg_match('/\.tar\.gz$/', $o); });
    array_walk($file_list,function(&$o,$key,$folder){ $o = substr($o,strlen($folder)); $o = substr($o,0,strlen($o)-7); },$folder);
    putLine('All templates online');
    foreach($file_list as $file){
        putLine($file);
    }

    return true;
}

function getOnlinetemplates($args){
    $url = 'download.openvz.org';
    $folder = 'template/precreated/';
    if(strlen($args)) $folder .= $args.'/';
    $conn = ftp_connect($url);
    $log = ftp_login($conn, 'anonymous','anonymous');
    $file_list = ftp_nlist($conn, $folder);
    $file_list = array_filter($file_list,function($o){ return preg_match('/\.tar\.gz$/', $o); });
    array_walk($file_list,function(&$o,$key,$folder){ $o = substr($o,strlen($folder)); $o = substr($o,0,strlen($o)-7); },$folder);
    
    return $file_list;
}

function download_template($args){
    global $servers;
    $url = 'download.openvz.org';
    $folder = 'template/precreated/';
    $args = explode(' ',$args);
    $host = $args[0];
    $template = $args[1];
    if(isset($args[2])) $folder .= $args[2].'/';

    if(!isset($servers[$host])){
        putLine($host.' is not known');
        return false;
    }

    putLine('Downloading requested template');
    runSSH($host,'wget http://'.$url.'/'.$folder.'/'.$template.'.tar.gz -O /vz/template/cache/'.$template.'.tar.gz --progress=bar:force');

    return true;
}

function shutdown_host($args){
    global $servers;
    global $reader;

    
    $servers_wanted = get_wanted_servers($args);
    if(!$servers_wanted) return false;

    $confirm = $reader->readLine('Are you sure you want to shutdown '.implode(', ',$servers_wanted).'? (y/N)> ');
    if(!in_array($confirm,array('y','Y','yes','Yes','YES'))){
        return true;
    }

    foreach($servers_wanted as $server_name){
        putLine('Shutting down '.$server_name);
        runSSH($server_name,'shutdown -h now');
        putLine('');
    }

    return true;
}

function reboot_host($args){
    global $servers;
    global $reader;

    $servers_wanted = get_wanted_servers($args);
    if(!$servers_wanted) return false;

    $confirm = $reader->readLine('Are you sure you want to reboot '.$args.'? (y/N)> ');
    if(!in_array($confirm,array('y','Y','yes','Yes','YES'))){
        return true;
    }

    foreach($servers_wanted as $server_name){
        putLine('Rebooting '.$server_name);
        runSSH($server_name,'reboot');
        putLine('');
    }
    
    return true;
}

function set_option($args){
    global $servers;
    global $reader;

    $araw = exploder(' ',$args);
    if(count($araw) != 3) return false;

    $host = $araw[0];
    $ctid = $araw[1];
    $command = $araw[2];

    if(!isset($servers[$host])) return false;

    switch($command){
        case 'memory':
            $ram = $reader->readLine('Amount of RAM?> ');
            $burst = $reader->readLine('Burstable RAM?> ');
            runSSH($host,'vzctl set '.$ctid.' --vmguarpages '.$ram.' --save');
            runSSH($host,'vzctl set '.$ctid.' --oomguarpages '.$ram.' --save');
            runSSH($host,'vzctl set '.$ctid.' --privvmpages '.$ram.':'.$burst.' --save');
            return true;
            break;
        case 'autoboot':
            $c = $reader->readLine('Start on Boot? (y/N)> ');
            $b = in_array($c,array('y','Y','yes','Yes','YES'));
            runSSH($host,'vzctl set '.$ctid.' --onboot '.($b?'yes':'no').' --save');
            return true;
            break;
        case 'cpuunit':
            $units = $reader->readLine('# of units?> ');
            runSSH($host,'vzctl set '.$ctid.' --cpuunits '.$units.' --save');
            return true;
            break;
        case 'cpulimit':
            $perc = $reader->readLine('Max CPU Usage (%)?> ');
            runSSH($host,'vzctl set '.$ctid.' --cpulimit '.$perc.' --save');
            return true;
            break;
        case 'diskquota':
            $c = $reader->readLine('Enable Disk Quotes? (y/N)> ');
            $b = in_array($c,array('y','Y','yes','Yes','YES'));
            runSSH($host,'vzctl set '.$ctid.' --diskquota '.($b?'yes':'no').' --save');
            return true;
            break;
        case 'diskspace':
            $units = $reader->readLine('Diskspace?> ');
            runSSH($host,'vzctl set '.$ctid.' --diskspace '.$units.' --save');
            return true;
        case 'ipadd':
            $ip = $reader->readLine('IP to add?> ');
            runSSH($host,'vzctl set '.$ctid.' --ipadd '.$ip.' --save');
            return true;
        case 'ipdel':
            $ip = $reader->readLine('IP to add?> ');
            runSSH($host,'vzctl set '.$ctid.' --ipdel '.$ip.' --save');
            return true;
            break;
    }

    return false;
}

function see_options($args){
    global $servers;
    
    $araw = exploder(' ',$args);
    if(count($araw) != 2) return false;

    $host = $araw[0];
    $ctid = $araw[1];

    if(!isset($servers[$host])) return false;

    return runSSH($host,'cat /etc/vz/conf/'.$ctid.'.conf');
}

function exploder($d,$s){
    $ar = explode($d,$s);
    $ar = array_filter($ar,function($v){return !empty($v); });
    return $ar;
}

function raw($args){
    global $servers;
    $args = explode(' ',$args,2);
    $host = $args[0];
    $command = $args[1];

    if(!isset($servers[$host])){
        putLine($host.' is not known');
        return false;
    }

    runSSH($host,$command);

    return true;
}

function help($args){
    global $function_mapping;
    if(strlen($args)){
        if(isset($function_mapping[$args])){
            $command = $args;
            $info = $function_mapping[$args];
            putLine($command.' '.$info['usage']);
            putLine("\t".$info['desc']);
            putLine('');
        }
        elseif($args == 'all'){
            foreach($function_mapping as $command=>$info){
                putLine($command.' '.$info['usage']);
                putLine("\t".$info['desc']);
                putLine('');
            }
        }
        else{
            putLine('Help for command not found');
        }
    }
    else{
        foreach($function_mapping as $command=>$info){
            putLine($command.' '.$info['usage']);
        }
    }
    return true;
}

function clear_screen($args){
    system('clear');
    return true;
}

function quit_program($args){
    global $reader;
    unset($reader);
    exit(1);
}

function showBanner(){
    putLine('######################################');
    putLine('#             VzControl              #');
    putLine('#           OpenVZ Manager           #');
    putLine('#                                    #');
    putLine('# Created By MrKMG <kevin@mrkmg.com> #');
    putLine('# Type `help` to start               #');
    putLine('#                               v0.2 #');
    putLine('######################################');
    putLine('');
}

function writeINIFile($location){
    file_put_contents($location,
'; This is a VzControl configuration file

[server1]
host = server1.domain

[server2]
host = 1.2.3.4
port = 2222
'
);
}

?>
