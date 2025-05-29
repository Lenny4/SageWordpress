import {
  ErrorMessageInterface,
  FieldInterface,
  FormContentInterface,
  FormInputOptions,
  FormInterface,
  InputInterface,
} from "../interface/InputInterface";
import React, { Dispatch, SetStateAction } from "react";
import { TabInterface } from "../interface/TabInterface";

export function transformOptionsObject(
  obj: Record<string | number, string>,
): FormInputOptions[] {
  return Object.entries(obj).map(([key, label]) => ({
    label,
    value: key.toString(),
  }));
}

export async function isValidGeneric(
  values: Record<string, InputInterface>,
  setValues: Dispatch<SetStateAction<Record<string, InputInterface>>>,
) {
  let hasError = false;
  let errorMessages: ErrorMessageInterface[] = [];
  for (const fieldName in values) {
    if (values[fieldName].validator) {
      const errorMessage = await values[fieldName].validator.functionName({
        ...(values[fieldName].validator.params ?? {}),
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
      [prop]: { ...v[prop], value: event.target.value as string, error: "" },
    };
    isValidGeneric(result, setValues);
    return result;
  });
};

export const handleChangeSelectGeneric = (
  event: React.ChangeEvent<HTMLSelectElement>,
  prop: any,
  setValues: Dispatch<SetStateAction<Record<string, InputInterface>>>,
) => {
  setValues((v) => {
    const result = {
      ...v,
      [prop]: { ...v[prop], value: event.target.value as string, error: "" },
    };
    isValidGeneric(result, setValues);
    return result;
  });
};

export const handleChangeCheckboxGeneric = (
  event: React.ChangeEvent<HTMLInputElement>,
  prop: any,
  setValues: Dispatch<SetStateAction<Record<string, InputInterface>>>,
) => {
  setValues((v) => {
    const result = {
      ...v,
      [prop]: { ...v[prop], value: event.target.checked, error: "" },
    };
    isValidGeneric(result, setValues);
    return result;
  });
};

export const getKeyFromName = (name: string) => {
  return name
    .match(/\[[^\]]+\]/)[0]
    .replace("[", "")
    .replace("]", "");
};

export const onSubmitForm = (
  form: FormInterface,
  formSelector: string,
  isValidForm: boolean,
  onStart?: () => void,
  onDone?: () => void,
): void => {
  $(formSelector).on("submit", (e) => {
    if (isValidForm) {
      return;
    }
    e.preventDefault();
    if (onStart) {
      onStart();
    }
    handleFormIsValid(form.content)
      .then((result: boolean) => {
        console.log("result", result);
        // let hasError = false;
        // for (const tab of tabs) {
        //   if (tab.ref.current) {
        //     hasError = hasError || !tab.ref.current.isValid();
        //   }
        // }
        // if (!hasError) {
        //   isValidForm = true;
        //   $(formSelector).trigger("submit");
        // }
      })
      .finally(() => {
        if (onDone) {
          onDone();
        }
      });
  });
};

export const handleFormIsValid = async (
  formContent: FormContentInterface,
  result: boolean = true,
): Promise<boolean> => {
  if (formContent.tabs?.tabs) {
    for (const tab of formContent.tabs.tabs) {
      result = (await _validateTab(tab)) && result;
    }
  }
  if (formContent.Dom) {
    result = (await _validateDom(formContent.Dom)) && result;
  }
  if (formContent.fields) {
    for (const field of formContent.fields) {
      result = (await _validateField(field)) && result;
    }
  }
  if (formContent.table) {
    for (const item of formContent.table.items) {
      for (const line of item.lines) {
        if (line.field) {
          result = (await _validateField(line.field)) && result;
        }
        if (line.Dom) {
          result = (await _validateDom(line.Dom)) && result;
        }
      }
    }
  }
  if (formContent.children) {
    for (const child of formContent.children) {
      result = (await handleFormIsValid(child, result)) && result;
    }
  }
  return result;
};

const _validateTab = async (tab: TabInterface): Promise<boolean> => {
  return await _validateDom(tab.dom);
};

const _validateField = async (field: FieldInterface): Promise<boolean> => {
  if (field.initValues.validator) {
    return (await field?.ref?.current?.isValid()) ?? true;
  }
  return true;
};

const _validateDom = async (dom: any): Promise<boolean> => {
  if (!dom.ref?.current?.isValid) {
    return true;
  }
  return await dom.ref.current.isValid();
};

export const createFormContent = (formContent: FormContentInterface) => {
  const addRefToFields = (formContent: FormContentInterface) => {
    if (formContent.fields) {
      for (const field of formContent.fields) {
        field.ref ??= React.createRef();
      }
    }
    if (formContent.table?.items) {
      for (const item of formContent.table.items) {
        for (const line of item.lines) {
          if (line.field) {
            line.field.ref ??= React.createRef();
          }
        }
      }
    }
    if (formContent.children) {
      for (const child of formContent.children) {
        addRefToFields(child);
      }
    }
  };
  addRefToFields(formContent);
  return formContent;
};
