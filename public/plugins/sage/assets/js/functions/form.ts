import {
  ErrorMessageInterface,
  FieldInterface,
  FormContentInterface,
  FormInputOptions,
  FormInterface,
  InputInterface,
} from "../interface/InputInterface";
import React, { ChangeEvent, Dispatch, SetStateAction } from "react";

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

export function getFlatFields(form: FormInterface): FieldInterface[] {
  const result: FieldInterface[] = [];

  const extractField = (array: any) => {
    if (Array.isArray(array)) {
      for (const item of array) {
        if (item.name) {
          result.push(item);
        } else if (item.field?.name) {
          result.push(item.field);
        } else if (Array.isArray(item)) {
          extractField(item);
        }
      }
    }
  };

  const extract = (nodes: FormContentInterface[]) => {
    for (const node of nodes) {
      if (node.fields) {
        extractField(node.fields);
      }
      if (node.table?.items) {
        for (const item of node.table.items) {
          if (item.lines) {
            extractField(item.lines);
          }
        }
      }

      if (node.children) {
        extract(node.children);
      }
    }

    return result;
  };

  extract(form.content);
  return result;
}

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

export const handleChangeSelectGeneric = (
  event: ChangeEvent<HTMLSelectElement>,
  prop: any,
  setValues: Dispatch<SetStateAction<Record<string, InputInterface>>>,
) => {
  setValues((v) => {
    const result = {
      ...v,
      [prop]: {
        ...v[prop],
        value: event.target.value as string,
        error: "",
      },
    };
    setTimeout(() => {
      isValidGeneric(result, setValues);
    });
    return result;
  });
};
