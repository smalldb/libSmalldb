all: pdf doc

.PHONY: all clean doc png pdf


dia = $(wildcard *.dia)
pdf = $(patsubst %.dia,%.pdf,$(wildcard *.dia))
png = $(patsubst %.dia,%.png,$(wildcard *.dia))

png: $(png)
pdf: $(pdf)


doc:
	../../../core/bin/build-plugin-documentation.sh

clean:
	rm -f $(pdf) $(png)
	rm -rf doxygen/


%.png: %.dia
	dia -e $@ -t png $<

%.pdf: %.dia
	dia -e /dev/stdout -t pdf $< | pdfcrop - $@

