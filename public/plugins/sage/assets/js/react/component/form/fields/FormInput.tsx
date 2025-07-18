import * as React from "react";
import { useImperativeHandle, useRef } from "react";
import { Tooltip } from "@mui/material";
import {
  FieldInterface,
  FormValidInterface,
} from "../../../../interface/InputInterface";
import {
  handleChangeInputGeneric,
  isValidGeneric,
} from "../../../../functions/form";
import {
  CannotBeChangeOnWebsiteComponent,
  FieldTooltipComponent,
} from "./FormFieldComponent";
import { TOKEN } from "../../../../token";

export const FormInput = React.forwardRef(
  (
    {
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
      autoUppercase,
    }: FieldInterface,
    ref,
  ) => {
    const inputRef = useRef<any>(null);
    const nameField = `_${TOKEN}_` + name;
    const [values, setValues] = React.useState({
      [name]: {
        ...initValues,
        value: autoUppercase
          ? initValues.value.toString().toUpperCase()
          : initValues.value.toString(),
      },
    });

    const handleChange = (event: React.ChangeEvent<HTMLInputElement>) => {
      handleChangeInputGeneric(event, name, setValues, {
        autoUppercase: !!autoUppercase,
      });
    };

    useImperativeHandle(ref, () => ({
      async isValid(): Promise<FormValidInterface> {
        const valid = await isValidGeneric(values, setValues);
        return {
          valid: valid,
          details: [
            {
              valid: valid,
              ref: ref,
              dRef: inputRef,
            },
          ],
        };
      },
    }));

    React.useEffect(() => {
      if (triggerFormContentChanged) {
        triggerFormContentChanged(name, values[name].value);
      }
    }, [values[name].value]);

    return (
      <>
        <label
          htmlFor={nameField}
          style={{
            display: hideLabel ? "none" : "block",
          }}
        >
          <Tooltip title={name} arrow placement="top">
            <span>{label}</span>
          </Tooltip>
        </label>
        <div style={{ display: "flex" }}>
          <div style={{ flex: 1 }}>
            <input
              id={nameField}
              name={nameField}
              type={type ?? "text"}
              value={values[name].value}
              readOnly={readOnly || values[name].readOnly}
              onChange={handleChange}
              style={{ width: "100%" }}
              ref={inputRef}
            />
          </div>
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
