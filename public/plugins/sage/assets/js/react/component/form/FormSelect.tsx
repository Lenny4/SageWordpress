import * as React from "react";
import { ChangeEvent } from "react";
import { FieldInterface } from "../../../interface/InputInterface";
import { Tooltip } from "@mui/material";
import {
  CannotBeChangeOnWebsiteComponent,
  FieldTooltipComponent,
} from "./FormFieldComponent";

export const FormSelect: React.FC<FieldInterface> = ({
  label,
  name,
  readOnly,
  hideLabel,
  options = [],
  errorMessage,
  cannotBeChangeOnWebsite,
  tooltip,
  initValues,
  triggerFormContentChanged,
}) => {
  const [values, setValues] = React.useState(initValues);
  const hasOption = !!options.find(
    (o) => o.value.toString() === values.value.toString(),
  );
  const handleChangeSelect = (event: ChangeEvent<HTMLSelectElement>) => {
    setValues((v) => {
      return {
        ...v,
        value: event.target.value as string,
        error: "",
      };
    });
  };

  React.useEffect(() => {
    if (triggerFormContentChanged) {
      triggerFormContentChanged(name, values.value);
    }
  }, [values.value]);

  name = "_sage_" + name;
  const thisReadOnly = readOnly || values.readOnly;
  return (
    <>
      <label
        htmlFor={name}
        style={{
          display: hideLabel ? "none" : "block",
        }}
      >
        <Tooltip title={name.replace("_sage_", "")} arrow placement="top">
          <span>{label}</span>
        </Tooltip>
      </label>
      <div style={{ display: "flex" }}>
        <div style={{ flex: 1 }}>
          <select
            id={name}
            name={name}
            value={values.value}
            onChange={handleChangeSelect}
            className={thisReadOnly ? "grayed-out-select" : ""}
            style={{ width: "100%" }}
          >
            {options.map((opt, index) => {
              return (
                <option
                  disabled={
                    opt.disabled ||
                    (thisReadOnly &&
                      !(
                        opt.value.toString() === values.value.toString() ||
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
