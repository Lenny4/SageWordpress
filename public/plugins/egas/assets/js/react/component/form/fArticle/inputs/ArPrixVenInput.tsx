import * as React from "react";
import { useImperativeHandle, useRef } from "react";
import { IconButton, Tooltip } from "@mui/material";
import InfoIcon from "@mui/icons-material/Info";
import { getTranslations } from "../../../../../functions/translations";
import {
  FormValidInterface,
  InputInterface,
} from "../../../../../interface/InputInterface";
import { handleChangeInputGeneric } from "../../../../../functions/form";
import { numberValidator } from "../../../../../functions/validator";
import { TOKEN } from "../../../../../token";

let translations: any = getTranslations();

export type ArPrixVenInputState = {
  defaultValue: number;
  arCoef: number | string;
  arPrixAch: number | string;
};

type FormState = {
  arPrixVen: InputInterface;
  realArPrixVen: InputInterface;
  valueLock: InputInterface;
};

export const ArPrixVenInput = React.forwardRef(
  ({ defaultValue, arCoef, arPrixAch }: ArPrixVenInputState, ref) => {
    const inputRef = useRef<any>(null);
    arCoef = Number(arCoef);
    arPrixAch = Number(arPrixAch);
    const getExpectedArPrixVen = () => {
      return Number((arCoef * arPrixAch).toFixed(2));
    };
    const [expectedArPrixVen, setExpectedArPrixVen] = React.useState(
      getExpectedArPrixVen(),
    );

    const getDefaultValue = (): FormState => {
      const v = defaultValue ?? 0;
      return {
        arPrixVen: { value: v.toString() },
        realArPrixVen: { value: v.toString() },
        valueLock: { value: v > 0 },
      };
    };
    const [values, setValues] = React.useState<FormState>(getDefaultValue());

    const handleChange =
      (prop: keyof FormState) =>
      (event: React.ChangeEvent<HTMLInputElement>) => {
        setValues((v) => {
          return {
            ...v,
            valueLock: {
              ...v.valueLock,
              value: true,
            },
          };
        });
        handleChangeInputGeneric(event, prop, setValues);
      };
    const handleRealArPrixVen = () => {
      setValues((v) => {
        return {
          ...v,
          realArPrixVen: {
            ...v.realArPrixVen,
            value:
              v.valueLock.value &&
              expectedArPrixVen !== Number(v.arPrixVen.value)
                ? Number(v.arPrixVen.value)
                : "0",
          },
        };
      });
    };

    const resetArPrixVen = () => {
      const newValue = getExpectedArPrixVen();
      setValues((v) => {
        return {
          ...v,
          arPrixVen: {
            ...v.arPrixVen,
            value: newValue.toString(),
          },
          valueLock: {
            ...v.valueLock,
            value: false,
          },
        };
      });
      setExpectedArPrixVen(newValue);
    };

    const isValid = async () => {
      const error = await numberValidator({
        value: values.arPrixVen.value,
      });
      setValues((v) => {
        return {
          ...v,
          arPrixVen: {
            ...v.arPrixVen,
            error: error,
          },
        };
      });
      return error === "";
    };

    useImperativeHandle(ref, () => ({
      async isValid(): Promise<FormValidInterface> {
        const valid = await isValid();
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
      isValid();
    }, [values.arPrixVen.value]); // eslint-disable-line react-hooks/exhaustive-deps

    React.useEffect(() => {
      handleRealArPrixVen();
    }, [expectedArPrixVen, values.arPrixVen.value]); // eslint-disable-line react-hooks/exhaustive-deps

    React.useEffect(() => {
      resetArPrixVen();
    }, [arCoef, arPrixAch]); // eslint-disable-line react-hooks/exhaustive-deps

    return (
      <>
        <label htmlFor={`_${TOKEN}_arPrixVen`}>
          <Tooltip title={"arPrixVen"} arrow placement="top">
            <span>{translations["fArticles"]["arPrixVen"]}</span>
          </Tooltip>
        </label>
        <div style={{ display: "flex", alignItems: "flex-start" }}>
          <div style={{ position: "relative", flex: 1 }}>
            <input
              id={`_${TOKEN}_arPrixVen`}
              name={`_${TOKEN}_arPrixVen`}
              type={"hidden"}
              value={values.realArPrixVen.value}
            />
            <input
              type={"number"}
              value={values.arPrixVen.value}
              onChange={handleChange("arPrixVen")}
              style={{ width: "100%" }}
              onBlur={() => {
                if (Number(values.arPrixVen.value) === 0) {
                  resetArPrixVen();
                }
              }}
              ref={inputRef}
            />
            {values.arPrixVen.error && (
              <div className={`${TOKEN}_error_field`}>
                {values.arPrixVen.error}
              </div>
            )}
          </div>
          {Number(values.arPrixVen.value) !== expectedArPrixVen &&
            Number(values.arPrixVen.value) > 0 && (
              <div style={{ position: "relative", top: "-2px" }}>
                <Tooltip title={translations.sentences.acPrixVenInput} arrow>
                  <IconButton>
                    <InfoIcon fontSize="small" />
                  </IconButton>
                </Tooltip>
              </div>
            )}
        </div>
      </>
    );
  },
);
