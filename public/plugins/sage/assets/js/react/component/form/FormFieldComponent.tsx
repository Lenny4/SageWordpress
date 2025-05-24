import * as React from "react";
import { FieldInterface } from "../../../interface/InputInterface";
import { getTranslations } from "../../../functions/translations";

let translations: any = getTranslations();

type State = {
  field: FieldInterface;
  values: any;
  transPrefix: string | undefined;
  handleChange: (
    prop: keyof any,
  ) => (event: React.ChangeEvent<HTMLInputElement>) => void | undefined;
  handleChangeSelect: (
    prop: keyof any,
  ) => (event: React.ChangeEvent<HTMLSelectElement>) => void | undefined;
};

export const FormFieldComponent: React.FC<State> = ({
  field,
  transPrefix,
  values,
  handleChange,
  handleChangeSelect,
}) => {
  const { name, DomField, readOnly, hideLabel, options, type } = field;
  let label = "";
  if (transPrefix && translations[transPrefix].hasOwnProperty(name)) {
    if (translations[transPrefix][name].hasOwnProperty("label")) {
      label = translations[transPrefix][name].label;
    } else {
      label = translations[transPrefix][name];
    }
  } else {
    label = translations.words[name] ?? name;
  }
  return (
    <DomField
      label={label}
      name={`_sage_${name}`}
      value={values[name].value}
      readOnly={!!readOnly || !!values[name].readOnly}
      onChange={handleChange(name)}
      onChangeSelect={handleChangeSelect(name)}
      hideLabel={hideLabel}
      options={options}
      type={type}
      errorMessage={values[name].error}
    />
  );
};
