// todo translate

export const stringValidator = async ({
  value,
  maxLength = null,
  canBeEmpty = false,
  canHaveSpace = true,
}: {
  value: string | null;
  maxLength: number | null;
  canBeEmpty?: boolean;
  canHaveSpace?: boolean;
}): Promise<string> => {
  value = (value ?? "").replace(/\s\s+/g, " ").trim();
  if (!canBeEmpty && value.length === 0) {
    return "Ce champ ne peut pas être vide";
  }
  if (maxLength !== null && value.length > maxLength) {
    return `Ce champ ne peut pas dépasser ${maxLength} caractères`;
  }
  if (!canHaveSpace && value.includes(" ")) {
    return "Ce champ ne peut pas avoir d'espace";
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
  value: string | number | null;
  canBeEmpty?: boolean;
  positive?: boolean;
  canBeFloat?: boolean;
  maxValue?: number;
  minValue?: number;
}): Promise<string> => {
  const isEmpty = value === "" || value === null;
  if (canBeEmpty !== false && isEmpty) {
    return "Ce champ ne peut pas être vide";
  }
  if (isEmpty) return ""; // allowed to be empty
  const numericValue = Number(value?.toString().trim());
  if (isNaN(numericValue)) {
    return "Ce champ n'est pas un nombre valide.";
  }
  if (!canBeFloat && !Number.isInteger(numericValue)) {
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
  return "";
};
