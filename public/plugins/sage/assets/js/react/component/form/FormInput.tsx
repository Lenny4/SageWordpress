import * as React from "react";
import { FieldInterface } from "../../../interface/InputInterface";
import { Tooltip } from "@mui/material";
import {
  CannotBeChangeOnWebsiteComponent,
  FieldTooltipComponent,
} from "./FormFieldComponent";

export const FormInput: React.FC<FieldInterface> = ({
  label,
  name,
  readOnly,
  hideLabel,
  type,
  errorMessage,
  cannotBeChangeOnWebsite,
  tooltip,
  initValues,
  triggerFormContentChanged,
}) => {
  const [values, setValues] = React.useState(initValues);

  const handleChange = (event: React.ChangeEvent<HTMLInputElement>) => {
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
          <input
            id={name}
            name={name}
            type={type ?? "text"}
            value={values.value}
            readOnly={readOnly || values.readOnly}
            onChange={handleChange}
            style={{ width: "100%" }}
          />
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
