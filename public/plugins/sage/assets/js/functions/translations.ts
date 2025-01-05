export const getTranslations = (): any => {
  let translationString = $("[data-sage-translation]").attr(
    "data-sage-translation",
  );
  let translations: any = [];
  if (translationString) {
    translations = JSON.parse(translationString);
  }
  return translations;
};
