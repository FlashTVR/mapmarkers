#!/usr/bin/python
from nbt import *
import sys
player = nbt.NBTFile(sys.argv[1],'rb')
pos = player["Pos"]
dim = player["Dimension"].value
print(str(pos[0].value) + "," + str(pos[1].value) + "," + str(pos[2].value) + "," + str(dim))
exit()
