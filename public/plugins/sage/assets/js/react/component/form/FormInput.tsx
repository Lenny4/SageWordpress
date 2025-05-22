import * as React from "react";
import { FormInputProps } from "../../../interface/InputInterface";
import { Tooltip } from "@mui/material";

export const FormInput: React.FC<FormInputProps> = ({
  label,
  name,
  value,
  readOnly,
  onChange,
  hideLabel,
  type,
  errorMessage,
}) => (
  <>
    <label
      htmlFor={name}
      style={{
        display: hideLabel ? "none" : "block",
      }}
    >
      <Tooltip title={name.replace("_sage_", "")} arrow>
        <span>{label}</span>
      </Tooltip>
    </label>
    <input
      id={name}
      name={name}
      type={type ?? "text"}
      value={value}
      readOnly={readOnly}
      onChange={onChange}
      style={{ width: "100%" }}
    />
    {errorMessage && <div className="sage_error_field">{errorMessage}</div>}
  </>
);
