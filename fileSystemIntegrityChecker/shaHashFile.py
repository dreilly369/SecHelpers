#! /usr/bin/python

import hashlib
import sys
import pwd
import os
import time
import re
import glob
import json
import MySQLdb as mdb
import datetime
from commands import *
from optparse import OptionParser

def readFromDatabase(con, projection, where):
    with con: 

        cur = con.cursor()
        cmdString = "SELECT "+projection+" FROM fingerprints WHERE "+where
        cur.execute(cmdString)

        rows = cur.fetchall()
        return rows

def updateDatabaseRow(con, dataSet, where):
    with con:
        updateString = "UPDATE fingerprints SET "
        for row in dataSet:
            updateString += row +"="+dataSet.get(row)+","
            
        #remove last commas
        updateString = updateString[:-1]
        updateString += " "+where
        cur = con.cursor()
        print (cur.execute(updateString))

def writeToDatabase(con, dataSet):
    with con:
        insertString = "INSERT INTO fingerprints("
        valueString = "VALUES("
        for row in dataSet:
            valueString += "\'"+re.escape(dataSet.get(row))+"\',";
            insertString += row +",";
        
        #remove last commas
        valueString = valueString[:-1]
        insertString = insertString[:-1]
        
        #cap the the strings 
        insertString += ")"
        valueString += ")"
        
        commandString = insertString+" "+valueString
        cur = con.cursor()
        cur.execute(commandString)

def hashDir(dir, blockSize, verbose=None):
    output = {}
    for f in os.listdir(dir):
        at = os.path.abspath(dir+"/"+f)
        if verbose is not None:
            print "checking "+at
        
        if os.path.isdir(at):
            hashDir(at,blockSize,verbose) # recurse into directory
        elif os.path.isfile(at):
            res = hashFile(at,blockSize,verbose)
            #print res
            
            
        else:
            print 'Unable to read ' + at
            print 'File        :', at
            print 'Path        :', os.path.abspath(dir)
            print 'Absolute    :', os.path.isabs(at)
            print 'Is File?    :', os.path.isfile(at)
            print 'Is Dir?     :', os.path.isdir(at)
            print 'Is Link?    :', os.path.islink(at)
            print 'Mountpoint? :', os.path.ismount(at)
            print 'Exists?     :', os.path.exists(at)
            print 'Link Exists?:', os.path.lexists(at)
            print 
        
    return output

#Hash a file and check it against the database        
def hashFile(fileName, blocksize=65536,verbose=None):
    hasher = hashlib.sha1()
    con = mdb.connect('localhost', 'ruemorgue', 'ruemorgue', 'file_fingerprints');    
    
    with open(fileName, 'rb') as afile:
        buf = afile.read(blocksize)
        while len(buf) > 0:
            hasher.update(buf)
            
            buf = afile.read(blocksize)
            
    fingerprint = hasher.hexdigest()
    if verbose is not None:
        print 'File        :', fileName
        print 'Path        :', os.path.abspath(fileName)
        print 'Absolute    :', os.path.isabs(fileName)
        print 'Is File?    :', os.path.isfile(fileName)
        print 'Is Dir?     :', os.path.isdir(fileName)
        print 'Is Link?    :', os.path.islink(fileName)
        print 'Mountpoint? :', os.path.ismount(fileName)
        print 'Exists?     :', os.path.exists(fileName)
        print 'Link Exists?:', os.path.lexists(fileName)
        print 
        
    row = {"file_name":os.path.basename(fileName),"file_location":fileName,"file_fingerprint":fingerprint}
    exists = readFromDatabase(con, "id,file_fingerprint","file_fingerprint='%s'" % fingerprint)
      
    if exists is None or len(exists) == 0:
        print "%s is new. FINGERPRINT: %s." % (fileName,fingerprint)
        writeToDatabase(con, row)
        
    else:
        for row in exists:
            if fingerprint == row[1]:
                print "%s has not changed." % fileName
            else:
                print "%s CHANGED." % fileName
                
    # print fingerprint
    return (row)


if __name__ == "__main__":
    parser = OptionParser()
    parser.add_option("-d", "--directory", dest="topDir",
                      help="The directory to start Hashing in")
    parser.add_option("-f", "--hash-file", dest="singleFile",
                      help="Set a single file to hash (cancels -d)")
    parser.add_option("-b", "--block-size", dest="blockSize", default=65536,
                      help="Change the default SHA1 block size from 65536. Only do this if you know what you are doing")
    parser.add_option("-v", "--verbose", dest="makeVerbose",
                      help="Output file details for each file scanned. ")         
    (options, args) = parser.parse_args()
    if options.topDir is None and options.singleFile is None:
        print "Must define a Directory (-d) or File (-f)"
        exit(0)
    
    verbUp = None
    #Turn on file info outputting
    if options.makeVerbose is not None:
        verbUp = True
    
    #Check if this is a directory scan
    if options.topDir is None:
        hashFile(options.singleFile, options.blockSize, verbUp)
    else:
        dataset = hashDir(options.topDir, options.blockSize, verbUp)
        print dataset