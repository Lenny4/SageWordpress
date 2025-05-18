import { FormInterface } from "../interface/InputInterface";

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

export function getFieldNames(form: FormInterface): string[] {
  const names: string[] = [];

  const extract = (nodes: any[]) => {
    for (const node of nodes) {
      if (node.fields) {
        for (const field of node.fields) {
          if (field.name) {
            names.push(field.name);
          }
        }
      }

      if (node.children) {
        extract(node.children);
      }
    }
  };

  extract(form.content);
  return names;
}
