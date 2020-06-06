#!/usr/bin/env python3
# -*- coding: utf-8 -*-
# runserver_socketio.py
# parameter
# 1 : listening port of daemon
# 2 : name of local system for CEC channel
# 3 : cecdevicetype for CEC channel
# 4 : ip adress of remote jeedom
# 5 : api key of remote jeedom
import os
import logging
import sys
import socketserver
import cec
from time import time, localtime, strftime, sleep
import urllib.request, urllib.error, urllib.parse
# compatibility with python 2.x , for 3.x rplace urllib2 by urllib request
import regex
#import exceptions
import threading

logging.basicConfig(level=logging.DEBUG,format='%(asctime)-15s - %(name)s: %(message)s')

__version__='0.96'
#print(cec)

cecList = ["TV","Recorder 1","Recorder 2","Tuner 1","Playback 1","Audio","Tuner 2","Tuner 3","Playback 2","Recorder 3","Tuner 4","Playback 3","Reserved 1","Reserved 2","Free use","Broadcast"]
cecKey  = {'00':'Select','01':'Up','02':'Down','03':'Left','04':'Right','05':'Right-Up','06':'Right-Down','07':'Left-Up','08':'Left-Down','09':'Root Menu','0a':'Setup Menu','0b':'Content Menu','0c':'Favorite Menu','0d':'Exit','0e':'Reserved','0f':'Reserved'}
cecKey2 = {'20':'0','21':'1','22':'2','23':'3','24':'4','25':'5','26':'6','27':'7','28':'8','29':'9','2a':'Dot','2b':'Enter','2c':'Clear','2d':'Reserved','2e':'Reserved','2f':'Next Favorite'}
cecKey3 = {'30':'Channel Up','31':'Channel Down','32':'Previous Channel','33':'Sound Select','34':'Input Select','35':'DisplayInformation','36':'Help','37':'Page Up','38':'Page Down','39':'Reserved','3a':'Reserved','3b':'Reserved','3c':'Reserved','3d':'Reserved','3e':'Reserved','3f':'Reserved'}
cecKey4 = {'40':'Power','41':'Volume Up','42':'Volume Down','43':'Mute','44':'Play','45':'Stop','46':'Pause','47':'Record','48':'Rewind','49':'Fast Forward','4a':'Eject','4b':'Forward','4c':'Backward','4d':'Stop-Record','4e':'Pause-Record','4f':'Reserved'}
cecKey5 = {'50':'Angle','51':'Sub picture','52':'Video on Demand','53':'Electronic Program Guide','54':'Timer Programming','55':'Initial Configuration','56':'Reserved','57':'Reserved','58':'Reserved','59':'Reserved','5a':'Reserved','5b':'Reserved','5c':'Reserved','5d':'Reserved','5e':'Reserved','5f':'Reserved'}
cecKey6 = {'60':'Play Function','61':'Pause-Play Function','62':'Record Function','63':'Pause-Record Function','64':'Stop Function','65':'Mute Function','66':'Restore Volume Function','67':'Tune Function','68':'Select Media Function','69':'Select A/V Input Function','6a':'Select Audio Input Function','6b':'Power Toggle Function','6c':'Power Off Function','6d':'Power On Function','6e':'Reserved','6f':'Reserved'}
cecKey7 = {'70':'Reserved','71':'F1 (Blue)','72':'F2 (Red)','73':'F3 (Green)','74':'F4 (Yellow)','75':'F5','76':'Data','77':'Reserved','78':'Reserved','79':'Reserved','7a':'Reserved','7b':'Reserved','7c':'Reserved','7d':'Reserved','7e':'Reserved','7f':'Reserved'}

cecKey.update(cecKey2)
cecKey.update(cecKey3)
cecKey.update(cecKey4)
cecKey.update(cecKey5)
cecKey.update(cecKey6)
cecKey.update(cecKey7)

#equipment = {'vendor':'inconnu','physicalAddress':'inconnu','logicalAddress':'inconnu','active':'inconnu','cecVersion':'inconnu','power':'inconnu','osdName':'inconnu'}
unscanned = {'vendor':'unscanned','physicalAddress':'unscanned','logicalAddress':'unscanned','active':'unscanned','cecVersion':'unscanned','power':'unscanned','osdName':'unscanned'}
eqInfo = {}
for key in cecList:
# bug can not use unscanned because reuse the same object 
    eqInfo[key] = {'vendor':'unscanned','physicalAddress':'unscanned','logicalAddress':'unscanned','active':'unscanned','cecVersion':'unscanned','power':'unscanned','osdName':'unscanned'}

eqScanned = []

server = ''

if len(sys.argv) > 1:
    PORT = int(sys.argv[1])
    HOST, PORT = "localhost", int(sys.argv[1])
else:
    PORT = 6000
    HOST, PORT = "localhost", 6000

# jeedomSystem=${2}
if len(sys.argv) > 2:
    jeedomSystem = sys.argv[2]
else:
    jeedomSystem = "Raspberry"

# jeedomSystem=${3}
if len(sys.argv) > 3:
    CECDEVICETYPE = sys.argv[3]
else:
    CECDEVICETYPE = "TunerDevice"

# jeedomIP=${4}
if len(sys.argv) > 4:
    jeedomIP = sys.argv[4]
else:
    jeedomIP = "localhost"

# jeedomApiKey=${5}
if len(sys.argv) > 5:
    jeedomApiKey = sys.argv[5]
else:
    jeedomApiKey = "jeedomApiKey"


jeedomCmd = "http://" + jeedomIP + "/core/api/jeeApi.php?apikey=" + jeedomApiKey + '&type=hdmiCec&value='


time_start = time()
print('Server started at ', strftime("%a, %d %b %Y %H:%M:%S +0000", localtime(time_start)), 'listening on port ', PORT)  


def polling(self, unstr):
    global cecList 
    print(unstr, time())
        
    for equipment in ['0','1','2','3','4','5','6','7','8','9','a','b','c','d','e']:
        if self.pyCecClient.GetLogicalAddressAdapter() == equipment:
            continue
        #envoi de la commande 8f : give device power status
        data = self.pyCecClient.GetLogicalAddressAdapter() + equipment +":8f"
        
        if lib.ProcessCommandTx(data):
            pass
            #print "Debug sendCommand give device power status Ok"
        else:
            #print "Debug sendCommand NOk"
            value = '{"logicalAddress":"' + cecList[int(equipment,16)] + '","status":"Off"}'
            #print("EVENT to notify send command", jeedomCmd + value)
            self.logger.debug('notify jeedom with command %s', jeedomCmd + value)
            urllib.request.urlopen(jeedomCmd + urllib.parse.quote(value)).read()


class jeedomRequestHandler(socketserver.BaseRequestHandler):
    def __init__(self, request, client_address, server):
        # initialization.
        self.logger = logging.getLogger('jeedomRequestHandler')
        self.pyCecClient = lib
        self.polling = MyTimer(5.0, polling, [self,"polling Cec"])
        socketserver.BaseRequestHandler.__init__(self, request,
                                                 client_address,
                                                 server)

    def start_response(self, code, contentType, data):
        log = logging.getLogger('start_reponse')
        log.debug('reponse code = %s data = %s', code, data)
        code = "HTTP/1.1 " + code + '\r\n'
        self.request.send(code.encode())
        response_headers = {
            'Content-Type': contentType +'; encoding=utf8',
            'Content-Length': len(data),
            'Connection': 'close',
        }
        response_headers_raw = ''.join('%s: %s\n' % (k, v) for k, v in response_headers.items())
        self.request.send(response_headers_raw.encode())
        self.request.send(b'\n')
        self.request.send(data.encode())
        return
        
    def sendCommand(self, dest, cmd, data):
        global eqInfo, cecList
        print("Debug sendCommand start")
        if self.pyCecClient.ProcessCommandTx(data):
            print("Debug sendCommand Ok")
            self.start_response('200 OK', "text/html", '<h1>'+cmd+' command done.</h1>' )
        else:
            print("Debug sendCommand NOk")
            value = '{"logicalAddress":"' + self.pyCecClient.lib.LogicalAddressToString(dest) + '","status":"Off"}'
            #print("EVENT to notify send command", jeedomCmd + value)
            self.logger.debug('notify jeedom with command %s', jeedomCmd + value)
            urllib.request.urlopen(jeedomCmd + urllib.parse.quote(value)).read()
            indice=cecList[dest]
            eqInfo[indice]["logicalAddress"]=indice
            eqInfo[indice]["power"]="Off"    
            self.start_response('200 OK', "text/html", '<h1>device off command '+cmd+' failed.</h1>')
        return

    def handle(self):
        global eqScanned, eqInfo, server
        #self.logger.debug('start handle')

        data = str(self.request.recv(1024), "utf-8").split('\n')[0]
        
        lst = data.split()
        stringcount = len(lst)
        #self.logger.debug('split len->"%s"', stringcount)
        if stringcount > 1:
            data = urllib.parse.unquote(urllib.parse.unquote(lst[1]))
        else:
            data = urllib.parse.unquote(urllib.parse.unquote(lst[1]))
        
        self.logger.debug('recv()->"%s"', data)
        cmd = 'unkown'
        data = data.split("?")
        #self.logger.debug('after split data -> %s', data)
        cmd = data[0]
        #self.logger.debug('cmd -> %s', cmd)
        cmd = cmd.split("/")[1]
        #self.logger.debug('cmd -> %s', cmd)
        arg = ''
        if len(data)>1:
            arg = data[1]
        
        #self.logger.debug('arg ->%s', arg)
        key = ''
        value = ''
        key2 = ''
        value2 = ''
        if arg:
            options = arg.split('&') 
            key = options[0].rpartition('=')[0]
            value = urllib.parse.unquote(options[0].rpartition('=')[2])
            if len(options) == 2:
                key2 = options[1].rpartition('=')[0]
                value2 = urllib.parse.unquote(options[1].rpartition('=')[2])
            
        #print('DEBUG = cmd=', cmd, ' arg ', arg, ' key ', key, ' value ', value, ' key2 ', key2, ' value2 ', value2)
        self.logger.debug('cmd ->%s arg=%s key=%s value=%s key2=%s value2=%s', cmd, arg, key, value, key2, value2 )
        #content_type = "text/javascript"
        #content_type = "text/html"
        #self.start_response('200 OK', content_type, data)

        if not cmd:
            content_type = "text/html"
            self.start_response('200 OK', content_type, '<h1>Welcome. Try a command ex : scan, stop, start.</h1>')
            return
            
        if cmd == 'scan':
            eqScanned = self.pyCecClient.ProcessCommandScan()
            data = '{'
            count = 1
            virgule = ''
            #on remet à inconnu la liste d'équipement
            unscanned = {'vendor':'unscanned','physicalAddress':'unscanned','logicalAddress':'unscanned','active':'unscanned','cecVersion':'unscanned','power':'unscanned','osdName':'unscanned'}
            for key, equipment in list(eqInfo.items()):
                eqInfo[key] = {'vendor':'unscanned','physicalAddress':'unscanned','logicalAddress':'unscanned','active':'unscanned','cecVersion':'unscanned','power':'unscanned','osdName':'unscanned'}
            for equipment in eqScanned:
                eqInfo[str(equipment[2])] = {'vendor':str(equipment[0]),'physicalAddress':str(equipment[1]),'logicalAddress':str(equipment[2]),'active':str(equipment[3]),'cecVersion':str(equipment[4]),'power':str(equipment[5]),'osdName':str(equipment[6])}
                data += virgule
                data += '"' + str(count) + '":{"vendor":"' + str(equipment[0]) + '","physicalAddress":"' + str(equipment[1]) + '","logicalAddress":"' + str(equipment[2]) + '","active":"' + str(equipment[3]) + '","cecVersion":"' + str(equipment[4]) + '","power":"' + str(equipment[5]) + '","osdName":"' + str(equipment[6]) + '"}'
                virgule = ','
                count += 1
            data += '}'
            # data = '{"1":{"vendor":'+str(equipments[0][0])+'},"2":{"vendor":'+str(equipments[1][0])+'}}'
            #print("DEBUG = data =", data)
            self.logger.debug('result scan data ->%s', data)

            content_type = "text/javascript"
            self.start_response('200 OK', content_type, data)
            return
        
        if cmd == 'dump':
            print("********** Dump - equipments :  All  **********")
            #print 'eqInfo=', eqInfo
            #equiment is dictionnary
            for key, equipment in list(eqInfo.items()):
                #print "vendor:" + str(equipment["vendor"])
                if str(equipment["logicalAddress"]) == 'unscanned':
                    print("equipment:", key, ' unscanned')
                else:
                    print("equipment:" + str(equipment["logicalAddress"]) + " power:" + str(equipment["power"]) + " physicalAddress:" + str(equipment["physicalAddress"]) + " active:" + str(equipment["active"]) + " cecVersion:" + str(equipment["cecVersion"]) + " vendor:" + str(equipment["vendor"]) + " osdName:" + str(equipment["osdName"]))
            
            print("********** Dump - equipments : scanned **********")
            #equipment is list ?
            for equipment in eqScanned:
                print("logicalAddress:" + str(equipment[2]) + " power:" + str(equipment[5]) + " physicalAddress:" + str(equipment[1]) + " active:" + str(equipment[3]) + " cecVersion:" + str(equipment[4]) + " vendor:" + str(equipment[0]) + " osdName:" + str(equipment[6]))
            
            content_type = "text/javascript"
            self.start_response('200 OK', content_type, '{"result":"ok"}')
            return
        
        #message ID : 72 - Turns the System Audio Mode On or Off (Directly addressed or Broadcast)
        if cmd == 'on':
            print("Debug Command on : start")
            #trouver le numéro de l'équipement entre 0 et 15
            dest = cecList.index(value)
            #if (dest == 0):
            #    data = "10:04"
            prefix = self.pyCecClient.GetLogicalAddressAdapter()+hex(dest)[2:]
            
            print("Debug Command on : prefix=", prefix)
            #initialize default value ! 
            data = prefix+":00"
            
            #tv
            #other way send power off : 6c  through UI
            #30:44:6C et 6D 
            if (dest == 0):
                data=prefix+":04"
            #tuner (1,2,3 or 4) => 3,6,7,10
            if (dest in [cecList.index('Tuner 1'),cecList.index('Tuner 2'),cecList.index('Tuner 3'),cecList.index('Tuner 4')]):
                data=prefix+":08:00"
            #amp
            if (dest in [cecList.index('Audio')]):
                #data=prefix+":72:01"
                data=prefix+":44:6d"
                print("Debug Command on : Audio=", data)
             #dvd
            if (dest in [cecList.index('Playback 1'),cecList.index('Playback 2'),cecList.index('Playback 3')]):
                data=prefix+":72:01"    
             #dvd
            if (dest in [cecList.index('Recorder 1'),cecList.index('Recorder 2'),cecList.index('Recorder 3')]):
                data=prefix+":72:01"    
            self.sendCommand(dest,cmd,data)
            return
            
        if cmd == 'off':
            #trouver le numéro de l'équipement entre 0 et 15
            dest = cecList.index(value)
            data = self.pyCecClient.GetLogicalAddressAdapter()+hex(dest)[2:]+":36"
            self.sendCommand(dest,cmd,data)
            return
            
        if cmd == 'setInput':
            #trouver le numéro de l'équipement entre 0 et 15
            dest = cecList.index('Broadcast')
            orig = self.pyCecClient.GetLogicalAddressAdapter()
            #orig = cecList.index(value)
            #default imput
            input = '13'
            if value2 == 'TV':
                input = '03'
            if value2 == 'HDMI1':
                input = '13'
            if value2 == 'HDMI2':
                input = '23'
            if value2 == 'HDMI3':
                input = '33'
            if value2 == 'HDMI4':
                input = '43'
            if value2 == 'HDMI5':
                input = '53'
            if value2 == 'HDMI6':
                input = '63'
            if value2 == 'HDMI7':
                input = '73'
            if value2 == 'HDMI8':
                input = '83'
                
            data = orig+hex(dest)[2:]+":82:"+input+":01"
            self.sendCommand(dest,cmd,data)
            #start_response('200 OK', [('Content-Type', 'text/html')])
            #TODO send feedback to update TV status
            value = '{"logicalAddress":"' + self.pyCecClient.lib.LogicalAddressToString(0) + '","input":"'+value2+'"}'
            #print("EVENT to notify send command", jeedomCmd + value)
            self.logger.debug('notify jeedom with command %s', jeedomCmd + value)
            urllib.request.urlopen(jeedomCmd + urllib.parse.quote(value)).read()
            return
           
        if cmd == 'osd':
            #la commande osd concerne toujour la TV
            dest = cecList.index('TV')
            #Hello world = 48:65:6C:6C:6F:20:77:6F:72:6C:64
            data = self.pyCecClient.GetLogicalAddressAdapter()+hex(dest)[2:]+":64:00:48:65:6C:6C:6F:20:77:6F:72:6C:64"
            self.sendCommand(dest,cmd,data)
            return
            
        if cmd == 'transmit':
            #trouver le numéro de l'équipement entre 0 et 15 
            #ici = key= destination, tandis que value est la commande complète
            dest = cecList.index(value)
            #Hello world = 48:65:6C:6C:6F:20:77:6F:72:6C:64
            data = self.pyCecClient.GetLogicalAddressAdapter()+hex(dest)[2:]+":"+value2
            self.sendCommand(dest,cmd,data)
            return
            
        if cmd == 'startPolling':
            self.polling.start()
            self.start_response('200 OK', 'text/html', '<h1>start Polling command done.</h1>')
            return
        
        if cmd == 'stopPolling':
            if self.polling != '':
                self.polling.stop()
            self.start_response('200 OK', 'text/html', '<h1>stop Polling command done.</h1>')
            return

        if cmd == 'test':
            if not value:
                value = ">> 01:90:00"
            value = self.pyCecClient.AnalyzeCommand(value)
            if value:
                #print("EVENT to notify send command", jeedomCmd + value)
                self.logger.debug('notify jeedom with command %s', jeedomCmd + value) 
                a = urllib.request.urlopen(jeedomCmd + urllib.parse.quote(value)).read()
                self.start_response('200 OK', 'text/html', '<h1>Test command done.</h1>')
                return
            
            '''
Executing the above commands goes as follows.

pi@raspberrypi ~ $ echo "tx 10 36" | cec-client -s #turn of tv
 
pi@raspberrypi ~ $ echo "tx 10 04" | cec-client -s #turn on tv
 
#Switch to HDMI1
pi@raspberrypi ~ $ echo "tx 4f 82 13 01" | cec-client -s
#Switch to HDMI2
pi@raspberrypi ~ $ echo "tx 4f 82 23 01" | cec-client -s


I have cec-client running on my raspberry pi, and I'm able to switch between active sources HDMI 1 - 4 on my Sony TV with:

echo "tx 4F 82 10 00" | cec-client -s
echo "tx 4F 82 40 00" | cec-client -s
What I haven't been able to achieve is switching back to TV as active source. Since the TV normally has ID 0.0.0.0, I would expect the following command would do the trick, but no response from TV:

echo "tx 4F 82 00 00" | cec-client -s

On my Samsung:

echo "txn 40 9D 00 00" | cec-client -s
works fine. So, do no Broadcast!

what about:
echo 'tx 4f 9d 10 00' | cec-client -s -d 1

4 - the source
f - broadcast
9d - <Inactive Source> command
10 00 - physical address (ID) of currently active source = 1.0.0.0

replace the ID with the one for currently active source.

It does NOT work on my Philips TV, but CEC standard says that "The TV may display its own internal tuner and shall send an <Active Source> with the address of the TV;...", so it may work on some other system.
I like tarapitha's answer because it has an explanation.

The TV switchs back to active source if there is no other device that reports to be active (in response to a [Request Active Source] message, so this is the reason why the [Inactive Source] message works.

The only problem is that [Inactive Source] message has to be directly addressed to the TV, so the correct frame would be 40 9d 10 00, if the physical address of the active source is 1.0.0.0

eman's answer probably worked because the TV is forgiving the wrong physical address, it just performs the active source request and finds no active source so sets itself as active.



currently, I juste use following command to switch to HDMI 2 of the TV
echo 'tx 10 82 20 00' | cec-client -d 1 -s
echo 'tx 10 82 20 01' | cec-client -d 1 -s

root@XBian:~# cat hdmi3.sh
#!/bin/sh
echo "tx 4f 82 13 00" | cec-client -s

http://raspberry-at-home.com/control-rpi-with-tv-remote/


'''
        self.logger.debug('Cmd = ->%s', cmd)    
        if cmd.startswith('stop') or cmd == 'stop':
            print('stop server requested')
            #todo close socket
            server.server_close()
            return self.end_daemon()
            sys.exit()
            # return end_daemon(start_response)

        print("Command not recognized :", cmd)
        self.logger.debug('Command not recognized ->%s', cmd)
        return self.not_found()


    def not_found(self):
        print("Debug not-found...")
        self.start_response('404 Not Found', 'text/html', '<h1>Not Found</h1>')
        return
    
    def end_daemon(self):
        self.start_response('200 Ok', 'text/html', '<h1>End daemon</h1>')
        return


class pyCecClient:
  cecconfig = cec.libcec_configuration()
  lib = {}
  # don't enable debug logging by default
  log_level = cec.CEC_LOG_TRAFFIC
  
  logicalAddressAdapter = ''
  physicalAddressAdapter = ''  

  def __init__(self):
    self.logger = logging.getLogger('pyCecClient')
    self.logger.debug('init pyCecClient start')
    self.SetConfiguration()
  
  def SetAddressAdapter(self,logicalAddress,physicalAddress):
      global logicalAddressAdapter, physicalAddressAdapter
      logicalAddressAdapter=hex(logicalAddress)[2:]
      physicalAddressAdapter=hex(physicalAddress).rstrip('L')
      print('physicaladdress=', physicalAddress, ' and :'+physicalAddressAdapter)
      
  def GetLogicalAddressAdapter(self):
      global logicalAddressAdapter
      return logicalAddressAdapter
  
  def GetPhysicalAddressAdapter(self,format):
      global physicalAddressAdapter
      if format == '':
          return physicalAddressAdapter
      if format == 'xx:xx':
          a = physicalAddressAdapter[2:].zfill(4)
          return a[0:2]+':'+a[2:4]
      if format == 'x.x.x.x':
          a = physicalAddressAdapter[2:].zfill(4)
          return a[0]+'.'+a[1]+'.'+a[2]+'.'+a[3]

  def GetDeviceType(self):
    if CECDEVICETYPE == "RecordingDevice":
        return cec.CEC_DEVICE_TYPE_RECORDING_DEVICE
    if CECDEVICETYPE == "TunerDevice":
        return cec.CEC_DEVICE_TYPE_TUNER
    if CECDEVICETYPE == "PaybackDevice":
        return cec.CEC_DEVICE_TYPE_PLAYBACK_DEVICE
    if CECDEVICETYPE == "AudioSystemDevice":
        return cec.CEC_DEVICE_TYPE_AUDIO_SYSTEM
    #default
    return  cec.CEC_DEVICE_TYPE_RECORDING_DEVICE

  # create a new libcec_configuration
  def SetConfiguration(self):
    print('jeedomsystem=', jeedomSystem)
    self.cecconfig.strDeviceName = jeedomSystem
    self.cecconfig.bActivateSource = 0
    self.cecconfig.deviceTypes.Add(self.GetDeviceType())
    #self.cecconfig.deviceTypes.Add(cec.CEC_DEVICE_TYPE_RESERVED)
    #ne fonctionne pas
    self.cecconfig.clientVersion = cec.LIBCEC_VERSION_CURRENT

  def SetLogCallback(self, callback):
    self.cecconfig.SetLogCallback(callback)

  def SetKeyPressCallback(self, callback):
    self.cecconfig.SetKeyPressCallback(callback)

  def SetCommandCallback(self, callback):
    self.cecconfig.SetCommandCallback(callback)

  def SetMenuStateCallback(self, callback):
    self.cecconfig.SetMenuStateCallback(callback)

  def SetSourceActivatedCallback(self, callback):
    self.cecconfig.SetSourceActivatedCallback(callback)

  # detect an adapter and return the com port path
  def DetectAdapter(self):
    retval = None
    adapters = self.lib.DetectAdapters()
    for adapter in adapters:
      print("found a CEC adapter:")
      print(("port:     " + adapter.strComName))
      print(("vendor:   " + hex(adapter.iVendorId)))
      print(("product:  " + hex(adapter.iProductId)))
      retval = adapter.strComName
    return retval

  # initialise libCEC
  def InitLibCec(self):
    self.logger.debug('init InitLibCec starting...')
    self.lib = cec.ICECAdapter.Create(self.cecconfig)
    # print libCEC version and compilation information
    print(("libCEC version " + self.lib.VersionToString(self.cecconfig.serverVersion) + " loaded: " + self.lib.GetLibInfo()))

    # search for adapters
    adapter = self.DetectAdapter()
    if adapter == None:
      print("No adapters found")
    else:
      if self.lib.Open(adapter):
        print("connection opened")
        self.MainLoop()
      else:
        print("failed to open a connection to the CEC adapter")

  # display the addresses controlled by libCEC
  def ProcessCommandSelf(self):
    addresses = self.lib.GetLogicalAddresses()
    strOut = "Addresses controlled by libCEC: "
    x = 0
    nothdmiCec = False
    while x < 15:
      if addresses.IsSet(x):
        if nothdmiCec:
          strOut += ", "
        strOut += self.lib.LogicalAddressToString(x)
        if self.lib.IsActiveSource(x):
          strOut += " (*)"
        nothdmiCec = True
      x += 1
    print(strOut)

  # send an active source message
  def ProcessCommandActiveSource(self):
    self.lib.SetActiveSource()

  # send a standby command
  def ProcessCommandStandby(self):
    self.lib.StandbyDevices(CECDEVICE_BROADCAST)

  # send a custom command
  def ProcessCommandTx(self, data):
    cmd = self.lib.CommandFromString(data)
    print(("transmit " + data))
    if self.lib.Transmit(cmd):
      print("command sent")
      return 1
    else:
      print("failed to send command")
      return 0

  # scan the bus and display devices that were found
  def ProcessCommandScan(self):
    print("Requesting CEC bus information ...")
    strLog = "CEC bus information\n===================\n"
    addresses = self.lib.GetActiveDevices()
    activeSource = self.lib.GetActiveSource()
    x = 0
    equipments = []
    while x < 15:
      if addresses.IsSet(x):
        vendorId        = self.lib.GetDeviceVendorId(x)
        physicalAddress = self.lib.GetDevicePhysicalAddress(x)
        active          = self.lib.IsActiveSource(x)
        cecVersion      = self.lib.GetDeviceCecVersion(x)
        power           = self.lib.GetDevicePowerStatus(x)
        osdName         = self.lib.GetDeviceOSDName(x)
        strLog += "device #" + str(x) +": " + self.lib.LogicalAddressToString(x)  + "\n"
        strLog += "address:       " + str(physicalAddress) + "\n"
        strLog += "active source: " + str(active) + "\n"
        strLog += "vendor:        " + self.lib.VendorIdToString(vendorId) + "\n"
        strLog += "CEC version:   " + self.lib.CecVersionToString(cecVersion) + "\n"
        strLog += "OSD name:      " + osdName + "\n"
        strLog += "power status:  " + self.lib.PowerStatusToString(power) + "\n\n\n"
        equipment = [self.lib.VendorIdToString(vendorId),physicalAddress,self.lib.LogicalAddressToString(x),active,cecVersion,self.lib.PowerStatusToString(power),osdName]
        equipments.append(equipment)
      x += 1
    print(strLog)
    return equipments

  # main loop, ask for commands
  def MainLoop(self):
    global eqInfo, eqScanned, cecList, cecKey, server
    self.logger.debug('init MainLoop starting, scan...')
    eqScanned = self.ProcessCommandScan()
    self.logger.debug('initial scan finished')
    print('osdname to look for =',self.cecconfig.strDeviceName)
    for equipment in eqScanned:
        eqInfo[str(equipment[2])] = {'vendor':str(equipment[0]),'physicalAddress':str(equipment[1]),'logicalAddress':str(equipment[2]),'active':str(equipment[3]),'cecVersion':str(equipment[4]),'power':str(equipment[5]),'osdName':str(equipment[6])}
        print('scanned :', equipment[2], ' name=',equipment[6])
        if equipment[6] == self.cecconfig.strDeviceName:
            self.SetAddressAdapter(cecList.index(equipment[2]),equipment[1])
            print('logicalAddress of adapter:', self.GetLogicalAddressAdapter())
    
    #send commande to signal this device is on before entering the main loop
    value = self.AnalyzeCommand("<< "+self.GetLogicalAddressAdapter()+"f:84:"+self.GetPhysicalAddressAdapter('xx:xx')+":01")
    #value = '{"logicalAddress":"Tuner1","status":"On"}'
    #print("EVENT to notify send command", jeedomCmd + value)
    self.logger.debug('notify jeedom with command %s', jeedomCmd + value)
    urllib.request.urlopen(jeedomCmd + urllib.parse.quote(value)).read()
    indice=cecList[int(self.GetLogicalAddressAdapter(),16)]
    eqInfo[indice]["logicalAddress"]=indice
    eqInfo[indice]["power"]="On"
     
    address = (HOST, PORT) 
    server = hdmiCecServer(address, jeedomRequestHandler)
    ip, port = server.server_address  # what port was assigned?
    
    self.logger.info('Server on %s:%s', ip, port)
    server.serve_forever()
    self.logger.info('Server ended')

    # Start the server in a thread
    '''
    t = threading.Thread(target=server.serve_forever)
    t.setDaemon(True)  # don't hang on exit
    t.start()
    print('Server loop running in thread:', t.getName())
    '''
    '''
    try:
        print('Listening on http://127.0.0.1:%s and on port 10843 (flash policy server)' % PORT)
        print('Host=', HOST)
        socketserver.TCPServer((HOST, PORT), jeedomHandler).serve_forever()
        # SocketIOServer(('', PORT), jeedomHandler(), resource="socket.io").serve_forever()
    except (KeyboardInterrupt, SystemExit):
        print('interception signal')
        sys.exit(0)
    '''
    print('before entering mainloop keyboard')
    runLoop = True
    

    while runLoop:
      command = input("Enter command:").lower()
      if command == 'q' or command == 'quit':
        runLoop = False
      elif command == 'self':
        self.ProcessCommandSelf()
      elif command == 'as' or command == 'activesource':
        self.ProcessCommandActiveSource()
      elif command == 'standby':
        self.ProcessCommandStandby()
      elif command == 'scan':
        self.ProcessCommandScan()
      elif command[:2] == 'tx':
        self.ProcessCommandTx(command[self.GetLogicalAddressAdapter():])
    print('Exiting...')

  # analyse received Command
  def AnalyzeCommand(self, command):
    global eqInfo, eqScanned, cecList
    # value=0 rien à faire
    value = 0
    
    #print "1"
    # 01:00:47:01 : message ID : 00 - Used as a response to indicate that the device does not support the requested message type, or that it cannot execute it at the present time (Directly addressed) parameters
    if command in [">> 63:9e:05"]:
        return value
    #print "2"
    # command 90
    # 0f:80:40:00:30:00
    try:   
        matchObj = regex.match('^>> ([0-9a-f])([0-9a-f]):90:([0-9a-f]{2})', command)
    except Exception:
        print("une erreur s est produite")
    if matchObj:
        if matchObj.group(3) in ['00','02']:
            status = "On"
        else:
            status = "Standby"
        try:     
            value = '{"logicalAddress":"' + self.lib.LogicalAddressToString(int(matchObj.group(1),16)) + '","status":"' + status + '"}'
        except Exception:
            print('fjghhhhhhhhhhhhhhh')
            
        #eqInfo[str(equipment[2])] = {'vendor':str(equipment[0]),'physicalAddress':str(equipment[1]),'logicalAddress':str(equipment[2]),'active':str(equipment[3]),'cecVersion':str(equipment[4]),'power':str(equipment[5]),'osdName':str(equipment[6])}
        indice=cecList[int(matchObj.group(1),16)]
        #print "indice =", indice
        #a=eqInfo[indice]['logicalAddress']
        #print "a=", a
        #eqInfo[indice]['logicalAddress']=indice
        #print 'resultat=',eqInfo[indice]['logicalAddress']
        #print 'eqInfo=', eqInfo
        #print 'before', eqInfo[cecList[int(matchObj.group(1),16)]]["logicalAddress"]
        #eqInfo[cecList[int(matchObj.group(1),16)]]['logicalAddress']=cecList[int(matchObj.group(1))]
        eqInfo[indice]["logicalAddress"]=indice
        #print 'after', eqInfo[cecList[int(matchObj.group(1),16)]]["logicalAddress"]
        #print 'eqInfo=', eqInfo
        #print 'before', eqInfo[cecList[int(matchObj.group(1),16)]]['power']
        eqInfo[indice]["power"]=status
        #print 'after', eqInfo[cecList[int(matchObj.group(1),16)]]['power']
        #print 'eqInfo=', eqInfo
        return value
    #print "3"
    # command 36
    matchObj = regex.match('^>> ([0-9a-f])f:36', command)
    if matchObj:
        value = '{"logicalAddress":"' + self.lib.LogicalAddressToString(int(matchObj.group(1),16)) + '","status":"Standby"}'
        indice=cecList[int(matchObj.group(1),16)]
        eqInfo[indice]["logicalAddress"]=indice
        eqInfo[indice]["power"]="Standby"
        return value
    #print "4"
    # command 83
    matchObj = regex.match('^>> ([0-9a-f])f:83', command)
    if matchObj:
        value = '{"logicalAddress":"' + self.lib.LogicalAddressToString(int(matchObj.group(1),16)) + '","info":"Request phys address"}'
        return value
    #print "5"
    # command 84 :message ID : 84 - Used to inform all other devices of the mapping
    # between physical and logical address of the initiator (Broadcast)
    # 5F:84:10:00:05
    #attention dans certains cas, ce n'est que du standby : affiner avec le contexte ? ou via une confirmation explicite
    #
    #générer ce message pour lever le doute pour un équipement de type Audio
    #message ID : 7D - Requests the status of the System Audio Mode (Directly addressed)
    #
    # 
    #incertitude sur 5f:84:10:00:05 = peut signifier On, mais aussi parfpis standby ; à déterminer suivant contexte
    matchObj = regex.match('^>> ([0-9a-f])f:84:[0-9a-f][0-9a-f]:[0-9a-f][0-9a-f]:(0[0-5])[.]*', command)
    if matchObj:
        #5f:84:10:00:05 or 
        data = self.GetLogicalAddressAdapter()+matchObj.group(1)+':8f'
        if self.ProcessCommandTx(data):
            print("msg 8f sent to check if device is On or Standby")
        else:
            print("Warning : error sending msg 8f to check if device is On or Standby")
        #value = '{"logicalAddress":"' + self.lib.LogicalAddressToString(int(matchObj.group(1),16)) + '","info":"Alive"}'
        return value
    
    matchObj = regex.match('^>> ([0-9a-f])f:84:[0-9a-f][0-9a-f]:[0-9a-f][0-9a-f]:[0-9a-f][0-9a-f][.]*', command)
    if matchObj:
        value = '{"logicalAddress":"' + self.lib.LogicalAddressToString(int(matchObj.group(1),16)) + '","status":"On"}'
        indice=cecList[int(matchObj.group(1),16)]
        eqInfo[indice]["logicalAddress"]=indice
        eqInfo[indice]["power"]="On"
        return value
    
    matchObj = regex.match('^<< ([0-9a-f])f:84:[0-9a-f][0-9a-f]:[0-9a-f][0-9a-f]:[0-9a-f][0-9a-f][.]*', command)
    if matchObj:
        value = '{"logicalAddress":"' + self.lib.LogicalAddressToString(int(matchObj.group(1),16)) + '","status":"On"}'
        indice=cecList[int(matchObj.group(1),16)]
        eqInfo[indice]["logicalAddress"]=indice
        eqInfo[indice]["power"]="On"
        return value
    #print "6"
    # command 85 : message ID : 85 - Used by a new device to discover the status of the system (Broadcast)
    # 5f:85      
    matchObj = regex.match('^>> ([0-9a-f])f:85', command)
    if matchObj:
        print("debug adr =", matchObj.group(1), ' name=', self.lib.LogicalAddressToString(int(matchObj.group(1),16))) 
        value = '{"logicalAddress":"' + self.lib.LogicalAddressToString(int(matchObj.group(1),16)) + '","info":"Alive"}'
        print("value", value)
        return value
    #print "7"
    # command 80 : 
    # *0f:80:00:00:10:00*|*0f:80:20:00:10:00*|*0f:80:30:00:10:00*|*0f:80:40:00:10:00*)
    #5f:80:16:00:14:00 ampli sur BD
    # HDMIx
    matchObj = regex.match('^>> ([0-9a-f])f:80:[0-9a-f][0-9a-f]:00:([0-9a-f][0-9a-f]):00', command)
    if matchObj:
        input = matchObj.group(2)
        
        inputInt = int(matchObj.group(2))
        if  inputInt == 10:
            input = "HDMI1"
        if  inputInt == 20:
            input = "HDMI2"
        if  inputInt == 30:
            input = "HDMI3"
        if  inputInt == 40:
            input = "HDMI4"    
        if  inputInt == 14:
            input = "BD"
        if  inputInt == 13:
            input = "DVD"
        if  inputInt == 12:
            input = "Game"
        if  inputInt == 16:
            input = "Sat/Catv"
        if  inputInt == 11:
            input = "Video"
        if  inputInt == 15:
            input = "SA CD/CD"
        value = '{"logicalAddress":"' + self.lib.LogicalAddressToString(int(matchObj.group(1),16)) + '","input":"' + input + '"}'
        return value
    #print "8"
    # command 82 :message ID : 82 - Used by a new source to indicate that it has started to transmit a stream OR used in response to a "Request Active Source" (Brodcast). This message is used in several features : One Touch Play,Routing Control
    # 1f:82:11:00
    # 0f:82:00:00
    matchObj = regex.match('^[>,<][>,<] ([0-9a-f])f:82:[0-9a-f][0-9a-f]:00', command)
    if matchObj:
        value = '{"logicalAddress":"' + self.lib.LogicalAddressToString(int(matchObj.group(1),16)) + '","info":"started to transmit"}'
        return value
    #print "9"
    # command 72 :message ID : 72 - Turns the System Audio Mode On or Off (Directly addressed or Broadcast)
    # *5f:72:00
    # attention 5f:72:00 peut aussi signifier mute pour un ampli
    matchObj = regex.match('^>> ([0-9a-f])f:72:([0-9a-f][0-9a-f])', command)
    if matchObj:
        if matchObj.group(2) == '01':
            status = "On"
        else:
            status = "Standby/Mute"        
        value = '{"logicalAddress":"' + self.lib.LogicalAddressToString(int(matchObj.group(1),16)) + '","status":"' + status + '"}'
        indice=cecList[int(matchObj.group(1),16)]
        eqInfo[indice]["logicalAddress"]=indice
        eqInfo[indice]["power"]=status
        return value
    #print "10"    
    #à vérifier : reçu après avoir demandé l'arrêt : pas eu d'autre message - +1 confirmé
    #hypothèse : arrêt du téléviseur
    #if command in ['>> 0f:a0:08:00:46:00:09:00:01'] :
    #    value = '{"logicalAddress":"' + self.lib.LogicalAddressToString(0) + '","status":"Standby"}'
    #    return value
    #0f:a0:08:00:46:00:09:00:01
    
    matchObj = regex.match('^>> ([0-9a-f]{2}):a0:(([0-9a-f]{2}:){3})(.*)', command)
    if matchObj:
        vendor=self.lib.VendorIdToString(int(matchObj.group(2).replace(":", ""),16))
        print('Special vendor command from ', vendor, ' = ', matchObj.group(4))
        if  matchObj.group(4) == '00:09:00:01':
            value = '{"logicalAddress":"' + self.lib.LogicalAddressToString(0) + '","status":"Standby"}'
            indice=cecList[0]
            eqInfo[indice]["logicalAddress"]=indice
            eqInfo[indice]["power"]="Standby"
            return value
        if  matchObj.group(4) == '00:13:00:10:00:00:01:00:00:00:00':
            #value = '{"logicalAddress":"' + self.lib.LogicalAddressToString(0) + '","info":"HDMI=>TV/AV"}'
            #correction le 5 mars 16 : input = TV sinon problème à l'allumage
            value = '{"logicalAddress":"' + self.lib.LogicalAddressToString(0) + '","input":"TV"}'
            return value
        if  matchObj.group(4) == '00:13:00:10:80:00:01:00:00:00:00':
            value = '{"logicalAddress":"' + self.lib.LogicalAddressToString(0) + '","info":"TV/AV=>HDMI"}'
            return value
        if  matchObj.group(4) == '00:13:00:10:00:60:60:00:00:00:00':
            value = '{"logicalAddress":"' + self.lib.LogicalAddressToString(0) + '","input":"PC"}'
            return value
        if  matchObj.group(4) in ['00:0a:00','00:08:00:00']:
            value = '{"logicalAddress":"' + self.lib.LogicalAddressToString(0) + '","info":"vendor standby => on ?"}'
            return value
        if  matchObj.group(4) in ['00:04:00:01','f0:00:00','f0:01:00:00:00']:
            value = '{"logicalAddress":"' + self.lib.LogicalAddressToString(0) + '","info":"unrecognized command :'+matchObj.group(4)+' from vendor:'+vendor+'"}'
            return value
        
    #print "11"
    matchObj = regex.match('^>> 0([0-9a-f]):8b:([0-9a-f][0-9a-f])', command)
    if matchObj:
        key = matchObj.group(2)
        value = '{"logicalAddress":"' + self.lib.LogicalAddressToString(int(matchObj.group(1),16)) + '","keyReleased":"'+cecKey[key]+ '"}'
        return value
    #print "12"
    matchObj = regex.match('^>> 0([0-9a-f]):44:([0-9a-f][0-9a-f])', command)
    if matchObj:
        key = matchObj.group(2)
        value = '{"logicalAddress":"' + self.lib.LogicalAddressToString(int(matchObj.group(1),16)) + '","keyPressed":"'+cecKey[key]+ '"}'
        return value
    #print "13"
    #command 87 message ID : 87 - Reports the Vendor ID of this device (Broadcast)
    #0f:87:08:00:46
    matchObj = regex.match('^>> ([0-9a-f])f:87:([0-9a-f]{2}:[0-9a-f]{2}:[0-9a-f]{2})', command)
    if matchObj:
        vendor=self.lib.VendorIdToString(int(matchObj.group(2).replace(":", ""),16))
        #value = '{"logicalAddress":"' + self.lib.LogicalAddressToString(int(matchObj.group(1))) + '","info":"vendor='+matchObj.group(1)+ '"}'
        value = '{"logicalAddress":"' + self.lib.LogicalAddressToString(int(matchObj.group(1),16)) + '","info":"vendor='+vendor+ '"}'
        return value
    
    
    #print "14"
    #message direct entre équipement
    #message ID : 00 - Used as a response to indicate that the device does not support the requested message type, or that it cannot execute it at the present time (Directly addressed)
    #47 = Set OSD name
    matchObj = regex.match('^>> ([0-9a-e])([0-9a-e]):00:47:[0-9a-f]', command)
    if matchObj:
        #deduction
        value = '{"logicalAddress":"' + self.lib.LogicalAddressToString(int(matchObj.group(1),16)) + '","status":"Standby"}'
        indice=cecList[int(matchObj.group(1),16)]
        eqInfo[indice]["logicalAddress"]=indice
        eqInfo[indice]["power"]="Standby"
        return value
         
    
    #print "14"
    #message direct entre équipement
    matchObj = regex.match('^>> ([0-9a-e])([0-9a-e]):([0-9a-f]{2}):([0-9a-f]{2})(.*)', command)
    if matchObj:
        msgID =  matchObj.group(3)
        print("direct message between equipement", msgID)
        if msgID == "00":
            #command 00message ID : 00 - Used as a response to indicate that the device does not support the requested message type, or that it cannot execute it at the present time (Directly addressed)
            #notify dest who can t execute the cmd to signal that dest is at least standby
            value = '{"logicalAddress":"' + self.lib.LogicalAddressToString(int(matchObj.group(1),16)) + '","info":"Request '+ matchObj.group(4) +' from '+self.lib.LogicalAddressToString(int(matchObj.group(2),16))+' cannot be executed"}'
            #value = '{"logicalAddress":"' + self.lib.LogicalAddressToString(int(matchObj.group(2))) + '","info":"Request '+ matchObj.group(4) +' to '+self.lib.LogicalAddressToString(int(matchObj.group(1)))+' cannot be executed"}'
            return value
        if msgID == "7a":
            #message ID : 7A - Reports an amplifier’s volume and mute status (Directly addressed)
            #notify dest who can t execute the cmd to signal that dest is at least standby
            value = '{"logicalAddress":"' + self.lib.LogicalAddressToString(int(matchObj.group(1),16)) + '","info":"Mute is '+ matchObj.group(4) +' and volume is '+matchObj.group(5)+'"}'
            #value = '{"logicalAddress":"' + self.lib.LogicalAddressToString(int(matchObj.group(2))) + '","info":"Request '+ matchObj.group(4) +' to '+self.lib.LogicalAddressToString(int(matchObj.group(1)))+' cannot be executed"}'
            return value
        if msgID == "7e":
            status = 'Unknown'
            if matchObj.group(4) == '01':
                status = 'On'
            if matchObj.group(4) == '00':
                status = 'Standby'
            #message ID : 7E - Reports the current status of the System Audio Mode (Directly addressed)
            #notify dest who can t execute the cmd to signal that dest is at least standby
            print("Audio system mode is ",status)
            value = '{"logicalAddress":"' + self.lib.LogicalAddressToString(int(matchObj.group(1),16)) + '","status":"'+ status +'"}'
            indice=cecList[int(matchObj.group(1),16)]
            eqInfo[indice]["logicalAddress"]=indice
            eqInfo[indice]["power"]=status
            return value
        
    #print "15"
    #message direct entre équipement
    matchObj = regex.match('^>> ([0-9a-e])([0-9a-e]):([0-9a-f]{2})', command)
    if matchObj:
        msgID =  matchObj.group(3)
        print("direct message between equipement", msgID)
        if msgID == "46":
            #message ID : 46 - Used to request the preferred OSD name of a device for use in menus associated with that device (Directly addressed)
            value = '{"logicalAddress":"' + self.lib.LogicalAddressToString(int(matchObj.group(1),16)) + '","info":"Request osdName to '+self.lib.LogicalAddressToString(int(matchObj.group(2),16))+'"}'
            #value = '{"logicalAddress":"' + self.lib.LogicalAddressToString(int(matchObj.group(2))) + '","info":"Request '+ matchObj.group(4) +' to '+self.lib.LogicalAddressToString(int(matchObj.group(1)))+' cannot be executed"}'
            return value
        if msgID == "8c":
            #message ID : 8C - Requests the Vendor ID from a device (Directly addressed)
            value = '{"logicalAddress":"' + self.lib.LogicalAddressToString(int(matchObj.group(1),16)) + '","info":"Request vendorId to '+self.lib.LogicalAddressToString(int(matchObj.group(2),16))+'"}'
            #value = '{"logicalAddress":"' + self.lib.LogicalAddressToString(int(matchObj.group(2))) + '","info":"Request '+ matchObj.group(4) +' to '+self.lib.LogicalAddressToString(int(matchObj.group(1)))+' cannot be executed"}'
            return value
        if msgID == "9f":
            #message ID : 9F - Used by a device to enquire which version of CEC the target supports (Directly addressed)
            value = '{"logicalAddress":"' + self.lib.LogicalAddressToString(int(matchObj.group(1),16)) + '","info":"Request Cec Version to '+self.lib.LogicalAddressToString(int(matchObj.group(2),16))+'"}'
            #value = '{"logicalAddress":"' + self.lib.LogicalAddressToString(int(matchObj.group(2))) + '","info":"Request '+ matchObj.group(4) +' to '+self.lib.LogicalAddressToString(int(matchObj.group(1)))+' cannot be executed"}'
            return value
        
    
    #print "16"
    #message ID : 32 - Used by a TV or another device to indicate the menu language (Broadcast)
    matchObj = regex.match('^>> ([0-9a-e])f:32:(.*)', command)
    if matchObj:
        ascii = matchObj.group(2).split(':')
        #print 'ascii', ascii
        country =''
        for char in ascii:
            country = country+chr(int(char,16))
        value = '{"logicalAddress":"' + self.lib.LogicalAddressToString(int(matchObj.group(1),16)) + '","language":"'+country+ '"}'
        return value
    
    print('Command to analyze', command)
    return 0


  # logging callback
  def LogCallback(self, level, time, message):
    if level > self.log_level:
      return 0
  
    if level == cec.CEC_LOG_ERROR:
      levelstr = "ERROR:   "
    elif level == cec.CEC_LOG_WARNING:
      levelstr = "WARNING: "
    elif level == cec.CEC_LOG_NOTICE:
      levelstr = "NOTICE:  "
    elif level == cec.CEC_LOG_TRAFFIC:
      levelstr = "TRAFFIC: "
    elif level == cec.CEC_LOG_DEBUG:
      levelstr = "DEBUG:   "
      
    time = time_start + time / 1000
    print((levelstr + "[" + strftime("%a, %d %b %Y %H:%M:%S +0000", localtime(time)) + "]     " + message))
    return 0

  # key press callback
  def KeyPressCallback(self, key, duration):
    print(("[key pressed] " + str(key)))
    return 0

# command callback
  def CommandCallback(self, command):
    print(("[command] " + command))
    value = self.AnalyzeCommand(command)
    if value:
      #print("EVENT to notify send command", jeedomCmd + value)
      self.logger.debug('notify jeedom with command %s', jeedomCmd + value) 
      urllib.request.urlopen(jeedomCmd + urllib.parse.quote(value)).read()
    return 0

# menu state callback
  def MenuStateCallback(self, state):
    print(("[menu state] " + str(state)))
    return 0

# source activated callback
  def SourceActivatedCallback(self, logicalAddress, activated):
    print(("[source activated] " + str(logicalAddress) + " activated=" + str(activated)))
    return 0

# logging callback
def log_callback(level, time, message):
  return lib.LogCallback(level, time, message)

# key press callback
def key_press_callback(key, duration):
  return lib.KeyPressCallback(key, duration)

# command callback
def command_callback(command):
  return lib.CommandCallback(command)

# menu state callback
def menu_state_callback(state):
  return lib.MenuStateCallback(state)

# source activated callback
def source_activated_callback(logicalAddress,activated):
  return lib.SourceActivatedCallback(logicalAddress,activated)


class MyTimer: 
    def __init__(self, tempo, target, args= [], kwargs={}): 
        self._target = target 
        self._args = args 
        self._kwargs = kwargs 
        self._tempo = tempo 
  
    def _run(self): 
        self._timer = threading.Timer(self._tempo, self._run) 
        self._timer.start() 
        self._target(*self._args, **self._kwargs) 
  
    def start(self): 
        self._timer = threading.Timer(self._tempo, self._run) 
        self._timer.start() 
  
    def stop(self): 
        self._timer.cancel() 
  
class hdmiCecServer(socketserver.TCPServer):
    def __init__(self, server_address, handler_class=jeedomRequestHandler):
        self.logger = logging.getLogger('hdmiCecServer')
        self.logger.debug('__init__')
        socketserver.TCPServer.allow_reuse_address = True
        socketserver.TCPServer.__init__(self, server_address, handler_class)
        return

    def server_activate(self):
        self.logger.debug('server_activate')
        socketserver.TCPServer.server_activate(self)
        return

    def serve_forever(self, poll_interval=0.5):
        self.logger.debug('waiting for request')
        self.logger.info(
            'Handling requests, press <Ctrl-C> to quit'
        )
        socketserver.TCPServer.serve_forever(self, poll_interval)
        return

    def handle_request(self):
        #self.logger.debug('handle_request')
        return socketserver.TCPServer.handle_request(self)

    def verify_request(self, request, client_address):
        #self.logger.debug('verify_request(%s, %s)', request, client_address)
        return socketserver.TCPServer.verify_request(
            self, request, client_address,
        )

    def process_request(self, request, client_address):
        self.logger.debug('process_request(%s, %s)', request, client_address)
        return socketserver.TCPServer.process_request(
            self, request, client_address,
        )

    def server_close(self):
        self.logger.debug('server_close')
        return socketserver.TCPServer.server_close(self)

    def finish_request(self, request, client_address):
        #self.logger.debug('finish_request(%s, %s)', request, client_address)
        return socketserver.TCPServer.finish_request(
            self, request, client_address,
        )

    def close_request(self, request_address):
        #self.logger.debug('close_request(%s)', request_address)
        return socketserver.TCPServer.close_request(
            self, request_address,
        )

    def shutdown(self):
        self.logger.debug('shutdown()')
        return socketserver.TCPServer.shutdown(self)


if __name__ == '__main__':
    logger = logging.getLogger('hdmiCec_server')
    logger.info('starting...')
    
    # initialise libCEC
    lib = pyCecClient()
    lib.SetLogCallback(log_callback)
    lib.SetKeyPressCallback(key_press_callback)
    lib.SetCommandCallback(command_callback)
    lib.SetMenuStateCallback(menu_state_callback)
    lib.SetSourceActivatedCallback(source_activated_callback)
    # initialise libCEC and enter the main loop
    lib.InitLibCec()
    logger.info('main hdmiCec_server end.')