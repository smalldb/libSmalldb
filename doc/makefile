
dia = $(wildcard *.dia)
pdf = $(patsubst %.dia,%.pdf,$(wildcard *.dia))
png = $(patsubst %.dia,%.png,$(wildcard *.dia))


all: pdf

png: $(png)
pdf: $(pdf)

.PHONY: all png pdf clean


clean:
	rm -f $(pdf) $(png)


%.png: %.dia
	dia -e $@ -t png $<

%.pdf: %.dia
	dia -e /dev/stdout -t pdf $< | pdfcrop - $@

