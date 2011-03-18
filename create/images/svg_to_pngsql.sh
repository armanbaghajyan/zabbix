#!/bin/bash

outputdir=output_png
pngcrushlog=pngcrush.log.txt
pngcrushbin=pngcrush
elementdir=elements
pngcrushoutput=pngcrushoutput.txt
sqlfile=images_mysql.sql

mkdir -p "$outputdir"

for svgfile in $elementdir/*.svg; do
	echo "converting $svgfile"
	for size in 24 48 64 96 128; do
		pngoutfile="$outputdir/$(basename ${svgfile%.svg}) ($size).png"
		echo "converting $pngoutfile to size $size"
		# we have to query image dimensions first, because export dimensions are used "as-is", resulting in a aquare rackmountable server, for example
		# inkscape option --query-all could be used, but it's not fully clear which layer is supposed to be "whole image"
		# crudely dropping decimal part, bash fails on it
		[[ "$(inkscape --without-gui --query-width $svgfile | cut -d. -f1)" -gt "$(inkscape --without-gui --query-height $svgfile | cut -d. -f1)" ]] && {
			dimension=width
		} || {
			dimension=height
		}
		inkscape --without-gui --export-$dimension=$size $svgfile --export-png="$pngoutfile" >> inkscape.log.txt|| exit 1
		$pngcrushbin -brute -reduce -e .2.png "$pngoutfile" >> $pngcrushoutput || exit 1
		echo "$pngoutfile : $(echo "$(stat -c %s "${pngoutfile%png}2.png")/$(stat -c %s "${pngoutfile}")*100" | bc -l)" >> $pngcrushlog
		mv "${pngoutfile%png}2.png" "$pngoutfile"
	done
done

for imagefile in $outputdir/*.png; do
	((imagecount++))
	echo "$imagefile"
	echo "INSERT INTO images (imageid,imagetype,name,image) VALUES ($imagecount,1,'${imagefile%.png}','$(hexdump -ve '"" 1/1 "%02X"' "$imagefile")';" >> $sqlfile
done