import {
  ErrorMessageInterface,
  FieldInterface,
  FormContentInterface,
  FormInputOptions,
  FormInterface,
  FormValidInterface,
  InputInterface,
  OptionChangeInputInterface,
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
  options?: OptionChangeInputInterface,
) => {
  setValues((v) => {
    let newValue = event.target.value as string;
    if (options?.autoUppercase) {
      newValue = newValue.toUpperCase();
    }
    const result = {
      ...v,
      [prop]: { ...v[prop], value: newValue, error: "" },
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

const _scrollElementIntoWindowView = (
  el: any,
  behavior: ScrollBehavior = "smooth",
) => {
  if (!el) return;

  const rect = el.getBoundingClientRect();
  const scrollTop = window.scrollY || document.documentElement.scrollTop;
  const viewportHeight = window.innerHeight;
  const targetScroll =
    scrollTop + rect.top - viewportHeight / 2 + rect.height / 2;

  window.scrollTo({
    top: targetScroll,
    behavior,
  });
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
      .then((result) => {
        if (!result.valid) {
          const domToScroll = result.details.find(
            (x) => x?.dRef?.current && !x.valid,
          )?.dRef?.current;
          if (domToScroll) {
            const tabParents = $(domToScroll)
              .parents("[id^='sage-tabpanel']")
              .toArray();
            for (const tabParent of tabParents) {
              const str = tabParent.id;
              const lastDashIndex = str.lastIndexOf("-");
              const firstPart = str.substring(0, lastDashIndex);
              const lastDigit = str.substring(lastDashIndex + 1);
              window.dispatchEvent(
                new CustomEvent(firstPart, { detail: lastDigit }),
              );
            }
            setTimeout(() => {
              _scrollElementIntoWindowView(domToScroll);
            }, 200);
          }
          return;
        }
        isValidForm = true;
        $(formSelector).trigger("submit");
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
  result: FormValidInterface | null = null,
): Promise<FormValidInterface> => {
  if (result === null) {
    result = {
      valid: true,
      details: [],
    };
  }
  if (formContent.Dom) {
    _mergeFormValidResult(result, await _validateDom(formContent.Dom));
  }
  if (formContent.fields) {
    for (const field of formContent.fields) {
      _mergeFormValidResult(result, await _validateField(field));
    }
  }
  if (formContent.table && typeof formContent.table.items !== "function") {
    for (const item of formContent.table.items) {
      for (const line of item.lines) {
        if (line.field) {
          _mergeFormValidResult(result, await _validateField(line.field));
        }
        if (line.Dom) {
          _mergeFormValidResult(result, await _validateDom(line.Dom));
        }
      }
    }
  }
  if (formContent.tabs?.tabs) {
    for (const [index, tab] of formContent.tabs.tabs.entries()) {
      _mergeFormValidResult(result, await _validateTab(tab));
    }
  }
  if (formContent.children) {
    for (const child of formContent.children) {
      await handleFormIsValid(child, result);
    }
  }
  return result;
};

const _mergeFormValidResult = (
  a: FormValidInterface,
  b: FormValidInterface,
) => {
  a.valid = a.valid && b.valid;
  a.details = a.details.concat(b.details);
};

const _validateTab = async (tab: TabInterface): Promise<FormValidInterface> => {
  return await _validateDom(tab.dom);
};

const _validateField = async (
  field: FieldInterface,
): Promise<FormValidInterface> => {
  if (field?.ref?.current) {
    if (field.initValues.validator) {
      const r: FormValidInterface = await field.ref.current.isValid();
      if (r) {
        return r;
      }
    }
  }
  return {
    valid: true,
    details: [],
  };
};

const _validateDom = async (dom: any): Promise<FormValidInterface> => {
  if (dom.ref?.current?.isValid) {
    const r: FormValidInterface = await dom.ref.current.isValid();
    if (r) {
      return r;
    }
  }
  return {
    valid: true,
    details: [],
  };
};

export const createFormContent = (formContent: FormContentInterface) => {
  const addRefToFields = (formContent: FormContentInterface) => {
    if (formContent.fields) {
      for (const field of formContent.fields) {
        field.ref ??= React.createRef();
      }
    }
    if (
      formContent.table?.items &&
      typeof formContent.table.items !== "function"
    ) {
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
