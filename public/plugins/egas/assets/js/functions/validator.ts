// todo translate

export const stringValidator = async ({
                                        value,
                                        maxLength,
                                        canBeEmpty,
                                        canHaveSpace,
                                        isReference,
                                      }: {
  value: string | null;
  maxLength?: number;
  canBeEmpty?: boolean;
  canHaveSpace?: boolean;
  isReference?: boolean;
}): Promise<string> => {
  value = (value ?? "").replace(/\s\s+/g, " ").trim();
  const isEmpty = value.length === 0;
  if (canBeEmpty === false && isEmpty) {
    return "Ce champ ne peut pas être vide.";
  }
  if (isEmpty) return ""; // allowed to be empty
  if (maxLength !== undefined && value.length > maxLength) {
    return `Ce champ ne peut pas dépasser ${maxLength} caractères.`;
  }
  if (canHaveSpace === false && value.includes(" ")) {
    return "Ce champ ne peut pas avoir d'espace.";
  }
  if (isReference && !/^[a-zA-Z0-9$%+.\/_-]+$/.test(value)) {
    return "Seuls les lettres, les chiffres et ces caractères $%+./_- sont acceptés.";
  }
  return "";
};

export const numberValidator = async ({
                                        value,
                                        canBeEmpty,
                                        positive,
                                        canBeFloat,
                                        maxValue,
                                        minValue,
                                      }: {
  value: string | number | null | undefined;
  canBeEmpty?: boolean;
  positive?: boolean;
  canBeFloat?: boolean;
  maxValue?: number;
  minValue?: number;
}): Promise<string> => {
  const isEmpty = value === "" || value === null || value === undefined;
  if (canBeEmpty !== true && isEmpty) {
    return "Ce champ ne peut pas être vide";
  }
  if (isEmpty) return ""; // allowed to be empty
  const numericValue = Number(value?.toString().trim());
  if (isNaN(numericValue)) {
    return "Ce champ n'est pas un nombre valide.";
  }
  if (canBeFloat === false && !Number.isInteger(numericValue)) {
    return "Les décimales ne sont pas autorisées.";
  }
  if (positive && numericValue < 0) {
    return "Ce champ ne peut être négatif.";
  }
  if (minValue !== undefined && numericValue < minValue) {
    return `La valeur doit être supérieure ou égale à ${minValue}.`;
  }
  if (maxValue !== undefined && numericValue > maxValue) {
    return `La valeur doit être inférieure ou égale à ${maxValue}.`;
  }
  if (!Number.isFinite(numericValue)) {
    return "La valeur est trop grande ou trop petite.";
  }
  if (
    numericValue > Number.MAX_SAFE_INTEGER ||
    numericValue < Number.MIN_SAFE_INTEGER
  ) {
    return `La valeur doit être comprise entre ${Number.MIN_SAFE_INTEGER} et ${Number.MAX_SAFE_INTEGER}.`;
  }
  return "";
};
