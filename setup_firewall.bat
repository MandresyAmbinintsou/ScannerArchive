@echo off
echo Configuration du Pare-feu Windows pour WAMP (Port 80)...
netsh advfirewall firewall add rule name="WAMP_Apache_Port_80" dir=in action=allow protocol=TCP localport=80
echo Configuration du Pare-feu Windows pour WAMP (Port 8001 - WebSocket)...
netsh advfirewall firewall add rule name="WAMP_Workerman_8001" dir=in action=allow protocol=TCP localport=8001
echo Termine.
pause
