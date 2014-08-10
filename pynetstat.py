#!/usr/bin/python

import sys
import pwd
import os
import re
import glob
from optparse import OptionParser

PROC_TCP = "/proc/net/tcp"
STATE = {
        '01':'ESTABLISHED',
        '02':'SYN_SENT',
        '03':'SYN_RECV',
        '04':'FIN_WAIT1',
        '05':'FIN_WAIT2',
        '06':'TIME_WAIT',
        '07':'CLOSE',
        '08':'CLOSE_WAIT',
        '09':'LAST_ACK',
        '0A':'LISTEN',
        '0B':'CLOSING'
        }


def _load():
    ''' Read the table of tcp connections & remove header  '''
    with open(PROC_TCP,'r') as f:
        content = f.readlines()
        content.pop(0)
    return content

def _hex2dec(s):
    return str(int(s,16))

def _ip(s):
    ip = [(_hex2dec(s[6:8])),(_hex2dec(s[4:6])),(_hex2dec(s[2:4])),(_hex2dec(s[0:2]))]
    return '.'.join(ip)

def _remove_empty(array):
    return [x for x in array if x !='']

def get_local_using_whitelist(conn_data,ports):
    badports = []
    for conn in conn_data:
        parts = conn[2].split(":")
        lport = parts[1]
        if lport not in ports and lport not in badports:
            badports.append(lport)
    return badports

def get_local_using_blacklist(conn_data,ports):
    badports = []
    for conn in conn_data:
        parts = conn[2].split(":")
        lport = parts[1]
        if lport in ports and lport not in badports:
            badports.append(lport)
    return badports
  
def get_remote_using_whitelist(conn_data,ports):
    badports = []
    for conn in conn_data:
        parts = conn[3].split(":")
        rport = parts[1]
        if rport not in ports and rport not in badports:
            badports.append(rport)
    return badports

def get_remote_using_blacklist(conn_data,ports):
    badports = []
    for conn in conn_data:
        parts = conn[3].split(":")
        rport = parts[1]
        if rport in ports and rport not in badports:
            badports.append(rport)
    return badports 

def _convert_ip_port(array):
    host,port = array.split(':')
    return _ip(host),_hex2dec(port)

def netstat(opts):
    '''
    Function to return a list with status of tcp connections at linux systems
    To get pid of all network process running on system, you must run this script
    as superuser
    '''

    content=_load()
    result = []
    for line in content:
        line_array = _remove_empty(line.split(' '))     # Split lines and remove empty spaces.
        l_host,l_port = _convert_ip_port(line_array[1]) # Convert ipaddress and port from hex to decimal.
        r_host,r_port = _convert_ip_port(line_array[2]) 
        tcp_id = line_array[0]
        state = STATE[line_array[3]]
        uid = pwd.getpwuid(int(line_array[7]))[0]       # Get user from UID.
        inode = line_array[9]                           # Need the inode to get process pid.
        pid = _get_pid_of_inode(inode)                  # Get pid prom inode.
        try:                                            # try read the process name.
            exe = os.readlink('/proc/'+pid+'/exe')
        except:
            exe = None

        nline = [tcp_id, uid, l_host+':'+l_port, r_host+':'+r_port, state, pid, exe]
        result.append(nline)
    return result

def _get_pid_of_inode(inode):
    '''
    To retrieve the process pid, check every running process and look for one using
    the given inode.
    '''
    for item in glob.glob('/proc/[0-9]*/fd/[0-9]*'):
        try:
            if re.search(inode,os.readlink(item)):
                return item.split('/')[2]
        except:
            pass
    return None

if __name__ == '__main__':
    parser = OptionParser()
    parser.add_option("-t", "--type", dest="listtype",default="w",
                      help="List can be either White(w) or Black(b).")
    parser.add_option("-w", "--warn",
                      dest="warnlevel", default=1,
                      help="Set the number of connections to trigger a warning Default 1")
    parser.add_option("-c", "--critical",
                      dest="criticallevel", default=1,
                      help="Set the number of connections to trigger a warning Default 1")
    parser.add_option("-l", "--local-ports",
                      dest="lports",
                      help="Comma Separated String of Local Port numbers to check (or ignore)")
    parser.add_option("-r", "--remote-ports",
                      dest="rports",
                      help="Comma Separated String of Remote Port numbers to check (or ignore)")

    (options, args) = parser.parse_args()
    
    conns = netstat(options)
    teh_ports = "";
    
    if options.lports is None and options.rports is None:
        print "PORTS UNKNOWN: Missing -l (local) or -r (remote) port list"
        sys.exit(-1)

    if options.listtype is "w":  
        if options.lports is None:
            wlviolators = get_remote_using_whitelist(conns,options.rports)
        else:
            wlviolators = get_local_using_whitelist(conns,options.lports)

        numbad = len(wlviolators)
        if numbad >= options.criticallevel:
            print "PORTS CRITICAL: %d open %s" % (int(numbad),str(wlviolators).strip('[]'))
            sys.exit(2)
        elif numbad >= options.warnlevel:
            print "PORTS WARNING: %s open %s" % (int(numbad),str(wlviolators).strip('[]'))
            sys.exit(1)
        else:
            print "PORTS OK: %d open" % (len(conns))
            sys.exit(0)
    else:
        if options.lports is None:
            blviolators = get_remote_using_blacklist(conns,options.rports)
        else:
            blviolators = get_local_using_blacklist(conns,options.lports)

        numbad = len(blviolators)
        if numbad >= options.criticallevel:
            print "PORTS CRITICAL: %d open %s" % (int(numbad),str(blviolators).strip('[]'))
            sys.exit(2)
        elif numbad >= options.warnlevel:
            print "PORTS WARNING: %d open %s" % (int(numbad),str(blviolators).strip('[]'))
            sys.exit(1)
        else:
            print "PORTS OK: %d open" % (len(conns))
            sys.exit(0)
    