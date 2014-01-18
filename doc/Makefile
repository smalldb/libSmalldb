all: pdf doc

.PHONY: all clean doc png pdf


dia = $(wildcard *.dia)
pdf = $(patsubst %.dia,%.pdf,$(wildcard *.dia))
png = $(patsubst %.dia,%.png,$(wildcard *.dia))

png: $(png)
pdf: $(pdf)


doc:
	( cat ../../../core/doc/Doxyfile \
		&& echo "PROJECT_NAME=\"`head -n 1 ../README.md`\"" \
		&& echo "PROJECT_BRIEF=" \
		&& echo "PROJECT_NUMBER=\"`git describe --tag --match "v[0-9]*"`\"" \
		) | ( cd .. && doxygen - )

clean:
	rm -f $(pdf) $(png)
	rm -rf doxygen/


%.png: %.dia
	dia -e $@ -t png $<

%.pdf: %.dia
	dia -e /dev/stdout -t pdf $< | pdfcrop - $@

