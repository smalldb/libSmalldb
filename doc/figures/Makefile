all: png pdf

.PHONY: all clean png pdf


dia = $(wildcard *.dia)
pdf = $(patsubst %.dia,%.pdf,$(wildcard *.dia))
png = $(patsubst %.dia,%.png,$(wildcard *.dia))

png: $(png)
pdf: $(pdf)

clean:
	rm -f $(pdf) $(png)

%.png: %.dia Makefile
	dia -e $@ -t png-libart $<

%.pdf: %.dia Makefile
	dia -e /dev/stdout -t pdf $< | pdfcrop --margins 5 - $@

