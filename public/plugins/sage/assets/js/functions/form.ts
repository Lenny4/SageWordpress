import {
  ErrorMessageInterface,
  FormContentInterface,
  FormInputOptions,
  FormInterface,
  InputInterface,
} from "../interface/InputInterface";
import React, { Dispatch, SetStateAction } from "react";

export const stringValidator = ({
  value,
  maxLength = null,
  canBeEmpty = false,
  canHaveSpace = true,
}: {
  value: string | null;
  maxLength: null | number;
  canBeEmpty: boolean;
  canHaveSpace: boolean;
}) => {
  value = (value?.replace(/\s\s+/g, " ") ?? "").trim() ?? "";
  if (!canBeEmpty && value.length === 0) {
    return "Ce champ ne peut pas être vide";
  }
  if (value.length > maxLength) {
    return "Ce champ ne peut pas dépasser " + maxLength + " caractères";
  }
  if (!canHaveSpace && value.includes(" ")) {
    return "Ce champ ne peut pas avoir d'espace";
  }
  return "";
};

export function transformOptionsObject(
  obj: Record<string | number, string>,
): FormInputOptions[] {
  return Object.entries(obj).map(([key, label]) => ({
    label,
    value: key.toString(),
  }));
}

export function isValidGeneric(
  values: Record<string, InputInterface>,
  setValues: Dispatch<SetStateAction<Record<string, InputInterface>>>,
) {
  let hasError = false;
  let errorMessages: ErrorMessageInterface[] = [];
  for (const fieldName in values) {
    if (values[fieldName].validator) {
      const errorMessage = values[fieldName].validator.functionName({
        ...values[fieldName].validator.params,
        value: values[fieldName].value,
      });
      const thisHasError = errorMessage !== "";
      hasError = hasError || thisHasError;
      if (thisHasError) {
        errorMessages.push({
          fieldName: fieldName,
          message: errorMessage,
        });
      }
    }
  }
  if (hasError) {
    setValues((v) => {
      const result = { ...v };
      for (const errorMessage of errorMessages) {
        result[errorMessage.fieldName].error = errorMessage.message;
      }
      return result;
    });
  }
  return !hasError;
}

export const handleChangeInputGeneric = (
  event: React.ChangeEvent<HTMLInputElement>,
  prop: any,
  setValues: Dispatch<SetStateAction<Record<string, InputInterface>>>,
) => {
  setValues((v) => {
    const result = {
      ...v,
      [prop]: { ...v[prop], value: event.target.value, error: "" },
    };
    setTimeout(() => {
      isValidGeneric(result, setValues);
    });
    return result;
  });
};

export const getDomsToSetParentFormData = (
  contents: FormContentInterface[],
) => {
  // todo améliorer pour que ce soit récursive et chercher autre part que dans table et tabs
  const doms: any[] = [];
  const extract = (contents: FormContentInterface[]) => {
    for (const content of contents) {
      for (const child of content.children) {
        if (child?.tabs) {
          for (const tab of child.tabs.tabs) {
            if (tab?.ref?.current?.getForm) {
              const form: FormInterface = tab?.ref?.current?.getForm();
              extract(form.content);
            }
          }
        }
        if (child?.table) {
          for (const item of child?.table.items) {
            for (const line of item.lines) {
              if (line?.Dom?.ref) {
                doms.push(line?.Dom);
              }
            }
          }
        }
        if (child?.Dom?.ref) {
          doms.push(child?.Dom);
        }
        if (child?.children) {
          extract(child.children);
        }
      }
    }
  };
  extract(contents);
  return doms;
};

export const getKeyFromName = (name: string) => {
  return name
    .match(/\[[^\]]+\]/)[0]
    .replace("[", "")
    .replace("]", "");
};
