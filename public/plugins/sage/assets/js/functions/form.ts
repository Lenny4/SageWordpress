export const stringValidator = (
  value: string | null,
  maxLength: null | number = null,
  canBeEmpty: boolean = false,
  canHaveSpace: boolean = true,
) => {
  value = (value?.replace(/\s\s+/g, " ") ?? "").trim() ?? "";
  if (!canBeEmpty && value.length === 0) {
    return "Ce champ ne peut pas être vide";
  }
  if (value.length > maxLength) {
    return "Ce champ ne peut pas dépassé " + maxLength + " caractères";
  }
  if (!canHaveSpace && value.includes(" ")) {
    return "Ce champ ne peut pas avoir d'espace";
  }
  return "";
};
