import * as React from "react";
import { useImperativeHandle } from "react";
import { Tooltip } from "@mui/material";
import { FieldInterface } from "../../../../interface/InputInterface";
import {
  handleChangeCheckboxGeneric,
  isValidGeneric,
} from "../../../../functions/form";
import {
  CannotBeChangeOnWebsiteComponent,
  FieldTooltipComponent,
} from "./FormFieldComponent";
import { TOKEN } from "../../../../token";

export const FormCheckbox = React.forwardRef(
  (
    {
      label,
      name,
      readOnly,
      hideLabel,
      errorMessage,
      cannotBeChangeOnWebsite,
      tooltip,
      initValues,
      triggerFormContentChanged,
    }: FieldInterface,
    ref,
  ) => {
    const nameField = `_${TOKEN}_` + name;
    const [values, setValues] = React.useState({
      [name]: {
        ...initValues,
        value: !!initValues.value, // Convert to boolean
      },
    });

    const handleChange = (event: React.ChangeEvent<HTMLInputElement>) => {
      handleChangeCheckboxGeneric(event, name, setValues);
    };

    useImperativeHandle(ref, () => ({
      async isValid(): Promise<boolean> {
        return await isValidGeneric(values, setValues);
      },
    }));

    React.useEffect(() => {
      if (triggerFormContentChanged) {
        triggerFormContentChanged(name, values[name].value.toString());
      }
    }, [values[name].value]);

    return (
      <>
        <div
          style={{
            display: "flex",
            alignItems: "center",
            gap: "0.5rem",
            marginBottom: "0.5rem",
          }}
        >
          <input
            id={nameField}
            name={nameField}
            type="checkbox"
            checked={values[name].value}
            readOnly={readOnly || values[name].readOnly}
            onChange={handleChange}
          />
          <label
            htmlFor={nameField}
            style={{
              display: hideLabel ? "none" : "auto",
            }}
          >
            <Tooltip title={name} arrow placement="top">
              <span>{label}</span>
            </Tooltip>
          </label>
        </div>

        <div style={{ display: "flex", alignItems: "center" }}>
          <CannotBeChangeOnWebsiteComponent
            cannotBeChangeOnWebsite={cannotBeChangeOnWebsite}
          />
          <FieldTooltipComponent tooltip={tooltip} />
        </div>
        {errorMessage && (
          <div className={`${TOKEN}_error_field`}>{errorMessage}</div>
        )}
        {values[name].error && (
          <div className={`${TOKEN}_error_field`}>{values[name].error}</div>
        )}
      </>
    );
  },
);
