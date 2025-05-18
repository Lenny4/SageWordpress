import * as React from "react";
import { FormInputProps } from "../../../interface/InputInterface";

export const FormSelect: React.FC<FormInputProps> = ({
  label,
  name,
  value,
  readOnly,
  onChangeSelect,
  hideLabel,
  options = [],
}) => {
  return (
    <>
      <label
        htmlFor={name}
        style={{
          display: hideLabel ? "none" : "block",
          marginBottom: 4,
        }}
      >
        {label}
      </label>
      <select
        id={name}
        name={name}
        value={value}
        onChange={onChangeSelect}
        disabled={readOnly}
        style={{ width: "100%" }}
      >
        {options.map((opt) => (
          <option disabled={opt.disabled} key={opt.value} value={opt.value}>
            {opt.label}
          </option>
        ))}
      </select>
    </>
  );
};
