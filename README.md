# SymconTwilightSwitch

IP-Symcon Modul zur helligkeitsabhängigen Steuerung der Außen-, Garten- bzw. Fassadenbeleuchtung. Dabei wird ein morgendlicher und abendlicher Zeitraum definiert in welcher die Beleuchtung eingeschalt sein darf.

Mittels eines Helligkeitssensor kann dann jeweils ein Schwellwert sowie eine Dauer definiert werden. Dabei wird bei Erreichen des morgendlichen Schwellwerts, der Einschaltzeitpunkt des Folgetages errechnet. Daher wird bei der Ersteinrichtung die Startzeit des morgendlichen Zeitraumes verwendet.

Der abendliche Einschaltzeitpunkt verhält sich wie ein klassicher Dämmerungsschalter. Sollte dieser vor dem abendlichen Zeitraum erreicht werden, findet ein Einschalten erst zu Beginn des hinterlegten Zeitraumes statt.

Ausgeschalt wird, sobald die Dauer abgelaufen ist oder aber das Ende des Zeitraumes erreicht wurde.
