import * as React from "react";
import { FormInputProps } from "../../../interface/InputInterface";
import { Tooltip } from "@mui/material";

export const FormSelect: React.FC<FormInputProps> = ({
  label,
  name,
  value,
  readOnly,
  onChangeSelect,
  hideLabel,
  options = [],
  errorMessage,
}) => {
  const hasOption = !!options.find(
    (o) => o.value.toString() === value.toString(),
  );
  return (
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
      <select
        id={name}
        name={name}
        value={value}
        onChange={onChangeSelect}
        className={readOnly ? "grayed-out-select" : ""}
        style={{ width: "100%" }}
      >
        {options.map((opt, index) => {
          return (
            <option
              disabled={
                opt.disabled ||
                (readOnly &&
                  !(
                    opt.value.toString() === value.toString() ||
                    (!hasOption && index === 0)
                  ))
              }
              key={opt.value}
              value={opt.value}
            >
              {opt.label}
            </option>
          );
        })}
      </select>
      {errorMessage && <div className="sage_error_field">{errorMessage}</div>}
    </>
  );
};
