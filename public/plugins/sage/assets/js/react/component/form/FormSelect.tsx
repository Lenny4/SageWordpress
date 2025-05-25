import * as React from "react";
import { FormInputProps } from "../../../interface/InputInterface";
import { Tooltip } from "@mui/material";
import {
  CannotBeChangeOnWebsiteComponent,
  FieldTooltipComponent,
} from "./FormFieldComponent";

export const FormSelect: React.FC<FormInputProps> = ({
  label,
  name,
  value,
  readOnly,
  onChangeSelect,
  hideLabel,
  options = [],
  errorMessage,
  cannotBeChangeOnWebsite,
  tooltip,
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
      <div style={{ display: "flex" }}>
        <div style={{ flex: 1 }}>
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
        </div>
        <CannotBeChangeOnWebsiteComponent
          cannotBeChangeOnWebsite={cannotBeChangeOnWebsite}
        />
        <FieldTooltipComponent tooltip={tooltip} />
      </div>
      {errorMessage && <div className="sage_error_field">{errorMessage}</div>}
    </>
  );
};
