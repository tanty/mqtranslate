# Extract translatable strings into the template
xgettext ../*.php \
    --from-code=UTF-8 \
    --default-domain=default \
    --language=PHP \
    --no-wrap \
    --keyword=__ \
    --keyword=_e \
    --package-name=mqTranslate \
    --package-version=2.7 \
    --copyright-holder="Qian Qi" \
    --output mqtranslate.pot

for lang in fr_FR sr_RS; do
    # Create empty files if the do not exist yet
    touch mqtranslate-$lang.po

    # Merge the .po files with the template
    msgmerge --update mqtranslate-$lang.po mqtranslate.pot

    # Convert all .po files into .mo
    pocompile mqtranslate-$lang.po mqtranslate-$lang.mo
done
