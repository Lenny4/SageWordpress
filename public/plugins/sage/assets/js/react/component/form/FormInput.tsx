import * as React from "react";
import { FormInputProps } from "../../../interface/InputInterface";

export const FormInput: React.FC<FormInputProps> = ({
  label,
  name,
  value,
  readOnly,
  onChange,
}) => (
  <>
    <label htmlFor={name} style={{ display: "block", marginBottom: 4 }}>
      {label}
    </label>
    <input
      id={name}
      name={name}
      type="text"
      value={value}
      readOnly={readOnly}
      onChange={onChange}
      style={{ width: "100%" }}
    />
  </>
);
