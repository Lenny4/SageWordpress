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
        <Tooltip title={name} arrow>
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
        {options.map((opt) => (
          <option
            disabled={
              opt.disabled ||
              (readOnly && opt.value.toString() !== value.toString())
            }
            key={opt.value}
            value={opt.value}
          >
            {opt.label}
          </option>
        ))}
      </select>
    </>
  );
};
