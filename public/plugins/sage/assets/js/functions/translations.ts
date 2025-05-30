import { TOKEN } from "../token";

export const getTranslations = (): any => {
  let translationString = $(`[data-${TOKEN}-translation]`).attr(
    `data-${TOKEN}-translation`,
  );
  let translations: any = [];
  if (translationString) {
    translations = JSON.parse(translationString);
  }
  return translations;
};
