
json = $(wildcard *.json)
pdf = $(patsubst %.json,%.pdf,$(wildcard *.json))
png = $(patsubst %.json,%.png,$(wildcard *.json))


all: pdf png

png: $(png)
pdf: $(pdf)

.PHONY: all png pdf clean


clean:
	rm -f $(pdf) $(png)

%.png: %.json renderer.php makefile
	./renderer.php $< | dot -Tpng -Gbgcolor=white -o $@

%.pdf: %.json renderer.php makefile
	./renderer.php $< | dot -Tpdf -o $@

